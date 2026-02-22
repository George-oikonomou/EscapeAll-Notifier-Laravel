<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\RoomAvailability;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class SyncRoomAvailabilityFast extends Command
{
    protected $signature = 'availability:sync-fast
        {--limit=0 : Max rooms to process (0 = all)}
        {--room-id= : Sync only a specific room by ID}
        {--batch-size=50 : How many rooms to send per Node process call}
        {--delay=1500 : Delay in ms between API calls inside Node (default: 1500)}
        {--months=10 : How many months ahead to check (default: 10, covers end of 2026)}
        {--show-logs : Display verbose output}
        {--dry-run : Show what would be done without making changes}
        {--save-to-storage : Save all raw API responses to storage/app/sync-data/availability-raw.json}
        {--debug-all : Save debug files for ALL rooms (not just empty ones)}';

    protected $description = 'Fast availability sync — batches all rooms into one Node process call per date range';

    private const DATE_BATCH_MONTHS = 2; // API returns max ~60 days, so we do 2-month passes

    public function handle(): int
    {
        ini_set('memory_limit', '-1'); // no memory limit — this runs large datasets

        $roomId    = $this->option('room-id');
        $limit     = (int) $this->option('limit');
        $batchSize = max(1, (int) $this->option('batch-size'));
        $delayMs   = max(500, (int) $this->option('delay'));
        $months    = max(1, (int) $this->option('months'));
        $showLogs  = (bool) $this->option('show-logs');
        $dryRun    = (bool) $this->option('dry-run');
        $dumpRaw   = (bool) $this->option('save-to-storage');
        $debugAll  = (bool) $this->option('debug-all');
        $startTime = microtime(true);

        // ── Get rooms ──
        $query = Room::whereNotNull('external_id')->where('external_id', '!=', '');

        if ($roomId) {
            $query->where('id', $roomId);
        }
        if ($limit > 0) {
            $query->limit($limit);
        }

        $rooms = $query->get();

        if ($rooms->isEmpty()) {
            $this->warn('No rooms found.');
            return Command::SUCCESS;
        }

        $totalRooms = $rooms->count();
        $this->info("[availability:sync-fast] Processing {$totalRooms} room(s)");

        $script = base_path('node/scripts/fetch-availability-fast.js');
        if (!is_file($script)) {
            $this->error("Node script not found: {$script}");
            return Command::FAILURE;
        }

        // Build date range passes: 2 months each
        $datePasses = [];
        $cursor = new \DateTime('today');
        $end = (new \DateTime('today'))->modify("+{$months} months");
        while ($cursor < $end) {
            $passFrom = (clone $cursor)->format('Y-m-d');
            $passUntil = min(
                (clone $cursor)->modify('+' . self::DATE_BATCH_MONTHS . ' months')->format('Y-m-d'),
                $end->format('Y-m-d')
            );
            $datePasses[] = ['from' => $passFrom, 'until' => $passUntil];
            $cursor->modify('+' . self::DATE_BATCH_MONTHS . ' months');
        }

        $passCount = count($datePasses);
        $this->line("  Date range: {$datePasses[0]['from']} → {$datePasses[$passCount-1]['until']} ({$months} months in {$passCount} passes)");
        $this->line("  Batch size: {$batchSize} rooms per Node call, delay={$delayMs}ms");
        $this->newLine();

        // Map external_id → Room model for later sync
        $roomsByExtId = $rooms->keyBy('external_id');
        $allExtIds = $rooms->pluck('external_id')->filter()->values()->all();

        // Split rooms into batches
        $roomBatches = array_chunk($allExtIds, $batchSize);
        $roomBatchCount = count($roomBatches);

        // Accumulate all slots per room across all date passes
        $allSlotsPerRoom = []; // extId → [ [date, time], ... ]

        // Prepare dump file (write incrementally)
        $dumpFile = null;
        if ($dumpRaw) {
            $syncDir = storage_path('app/sync-data');
            @mkdir($syncDir, 0775, true);
            $dumpFile = $syncDir . '/availability-raw.json';
            file_put_contents($dumpFile, "{\n"); // start JSON object
            $this->info("Raw dump will be written to: {$dumpFile}");
        }
        $dumpFirstEntry = true;

        $totalNodeCalls = $roomBatchCount * $passCount;
        $callNum = 0;

        foreach ($datePasses as $pi => $pass) {
            $passNum = $pi + 1;
            $this->info("═══ Date pass {$passNum}/{$passCount}: {$pass['from']} → {$pass['until']} ═══");
            $this->newLine();

            foreach ($roomBatches as $bi => $batchExtIds) {
                $callNum++;
                $batchNum = $bi + 1;
                $batchRoomCount = count($batchExtIds);

                $estSec = $batchRoomCount * ($delayMs / 1000 + 0.5) + 5;
                $this->info("  ── Room batch {$batchNum}/{$roomBatchCount} ── {$batchRoomCount} rooms (est. ~" . round($estSec) . "s)");

                $t = microtime(true);

                $args = [
                    'node', $script,
                    "from={$pass['from']}",
                    "until={$pass['until']}",
                    'serviceIds=' . implode(',', $batchExtIds),
                    'language=el',
                    'bookedBy=1',
                    'noGifts=false',
                    "delayMs={$delayMs}",
                ];

                $proc = new Process($args, base_path(), ['NODE_PATH' => base_path('node/automation/node_modules')]);
                $proc->setTimeout(600);

                // Build GUID→name map for readable logs
                $nameMap = [];
                foreach ($batchExtIds as $eid) {
                    $r = $roomsByExtId[$eid] ?? null;
                    if ($r) {
                        $nameMap[substr($eid, 0, 8)] = mb_substr($r->title, 0, 35);
                    }
                }

                // Stream stderr for live progress
                $proc->run(function ($type, $buffer) use ($showLogs, $nameMap) {
                    if ($type === Process::ERR && $showLogs) {
                        foreach (explode("\n", trim($buffer)) as $line) {
                            if (empty(trim($line))) continue;
                            $display = trim($line);
                            foreach ($nameMap as $guidPrefix => $name) {
                                if (str_contains($display, $guidPrefix)) {
                                    $display = str_replace($guidPrefix . '...', $name, $display);
                                    break;
                                }
                            }
                            $this->line("    📡 " . $display);
                        }
                    }
                });

                if (!$proc->isSuccessful()) {
                    $this->error("    Batch failed!");
                    if ($showLogs) {
                        $this->line($proc->getErrorOutput());
                    }
                    continue;
                }

                $batchElapsed = microtime(true) - $t;

                $output = trim($proc->getOutput());
                $results = json_decode($output, true);

                if (!is_array($results)) {
                    $this->error("    Invalid JSON output from Node");
                    continue;
                }

                $this->line(sprintf('    Node returned %d room(s) in %.1fs', count($results), $batchElapsed));

                // Process each room
                $passSlotCount = 0;
                foreach ($batchExtIds as $extId) {
                    $days = $results[$extId] ?? [];

                    // Save debug response for rooms with empty/no slots, or all rooms if --debug-all
                    $roomModel = $roomsByExtId[$extId] ?? null;
                    $shouldSaveDebug = $roomModel && ($debugAll || !is_array($days) || empty($days) || !$this->hasAnyAvailableSlots($days));

                    if ($shouldSaveDebug) {
                        $debugDir = $debugAll
                            ? storage_path('app/sync-data/availability-debug-all')
                            : storage_path('app/sync-data/availability-debug-empty');
                        if (!is_dir($debugDir)) {
                            mkdir($debugDir, 0775, true);
                        }
                        $safeExtId = preg_replace('/[^a-zA-Z0-9\-]/', '', $extId);
                        $debugFile = $debugDir . '/' . $roomModel->id . '_' . $safeExtId . '.json';

                        $debugEntry = [
                            'room_id' => $roomModel->id,
                            'room_title' => $roomModel->title,
                            'external_id' => $extId,
                            'pass_num' => $passNum,
                            'from' => $pass['from'],
                            'until' => $pass['until'],
                            'timestamp' => now()->toIso8601String(),
                            'days_count' => is_array($days) ? count($days) : 0,
                            'days_data' => $days,
                            'has_any_available' => $this->hasAnyAvailableSlots($days),
                        ];

                        // Append to debug file
                        $existingDebug = [];
                        if (file_exists($debugFile)) {
                            $existingContent = file_get_contents($debugFile);
                            $existingDebug = json_decode($existingContent, true) ?: [];
                        }
                        if (!isset($existingDebug['passes'])) {
                            $existingDebug['passes'] = [];
                        }
                        $existingDebug['passes'][] = $debugEntry;
                        $existingDebug['last_updated'] = now()->toIso8601String();
                        file_put_contents($debugFile, json_encode($existingDebug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                        if ($showLogs) {
                            $this->line("      📝 Debug saved: {$debugFile}");
                        }
                    }

                    // Dump raw response incrementally to file (never hold in memory)
                    if ($dumpRaw && $dumpFile) {
                        $roomTitle = ($roomsByExtId[$extId] ?? null)?->title ?? $extId;
                        $entry = [
                            'from' => $pass['from'],
                            'until' => $pass['until'],
                            'day_count' => is_array($days) ? count($days) : 0,
                            'data' => $days,
                        ];

                        $key = $extId . '__pass' . $passNum;
                        $prefix = $dumpFirstEntry ? '' : ",\n";
                        $dumpFirstEntry = false;
                        $line = $prefix . json_encode($key, JSON_UNESCAPED_UNICODE) . ': ' .
                                json_encode(['title' => $roomTitle, 'pass' => $entry], JSON_UNESCAPED_UNICODE);
                        file_put_contents($dumpFile, $line, FILE_APPEND);
                        unset($entry, $line); // free memory immediately
                    }

                    if (!is_array($days) || empty($days)) continue;

                    $slots = $this->extractSlots($days);
                    if (!empty($slots)) {
                        if (!isset($allSlotsPerRoom[$extId])) {
                            $allSlotsPerRoom[$extId] = [];
                        }
                        $allSlotsPerRoom[$extId] = array_merge($allSlotsPerRoom[$extId], $slots);
                        $passSlotCount += count($slots);
                    }
                }

                // Free the large output string
                unset($output, $results);

                $this->line("    +{$passSlotCount} slots from this batch");

                // Progress
                $totalElapsed = microtime(true) - $startTime;
                $eta = ($totalNodeCalls - $callNum) * ($totalElapsed / max($callNum, 1));
                $this->line(sprintf(
                    '    Progress: %d/%d calls | %.1fs elapsed | ETA: %.0fs (%.1f min)',
                    $callNum, $totalNodeCalls, $totalElapsed, $eta, $eta / 60
                ));
                $this->newLine();
            }
        }

        // Close dump file
        if ($dumpRaw && $dumpFile) {
            file_put_contents($dumpFile, "\n}", FILE_APPEND);
            $size = round(filesize($dumpFile) / 1024 / 1024, 1);
            $this->info("Raw API dump saved → {$dumpFile} ({$size} MB)");
            $this->newLine();
        }

        // ── Sync to database ──
        $this->info('═══ Syncing to database ═══');

        $totalSlots = 0;
        $totalCreated = 0;
        $totalDeleted = 0;
        $roomsWithSlots = 0;
        $roomsEmpty = 0;

        $fromDate = (new \DateTime('today'))->format('Y-m-d');

        foreach ($allExtIds as $extId) {
            $room = $roomsByExtId[$extId] ?? null;
            if (!$room) continue;

            $slots = $allSlotsPerRoom[$extId] ?? [];

            if (empty($slots)) {
                $roomsEmpty++;
                if ($showLogs) {
                    $this->line("  ○ {$room->title} → no available slots");
                }

                if (!$dryRun) {
                    $deleted = RoomAvailability::where('room_id', $room->id)
                        ->where('available_date', '>=', $fromDate)
                        ->delete();
                    $totalDeleted += $deleted;
                }
                continue;
            }

            // Deduplicate slots (in case date ranges overlap)
            $uniqueSlots = [];
            foreach ($slots as $s) {
                $key = $s['date'] . '|' . $s['time'];
                $uniqueSlots[$key] = $s;
            }
            $slots = array_values($uniqueSlots);

            $roomsWithSlots++;
            $totalSlots += count($slots);

            if ($showLogs) {
                $this->info("  ✓ {$room->title} → " . count($slots) . " slots");
            }

            if ($dryRun) {
                foreach (array_slice($slots, 0, 3) as $s) {
                    $this->line("      {$s['date']} {$s['time']}");
                }
                if (count($slots) > 3) {
                    $this->line("      ... and " . (count($slots) - 3) . " more");
                }
            } else {
                $syncResult = $this->syncRoomAvailabilities($room, $slots);
                $totalCreated += $syncResult['created'];
                $totalDeleted += $syncResult['deleted'];

                if ($showLogs && ($syncResult['created'] > 0 || $syncResult['deleted'] > 0)) {
                    $this->line("      DB: +{$syncResult['created']} created, -{$syncResult['deleted']} deleted");
                }
            }
        }

        $totalElapsed = microtime(true) - $startTime;
        $this->newLine();
        $this->info('═══ Summary ═══');
        $this->info(sprintf('Total time: %.1fs (%.1f min)', $totalElapsed, $totalElapsed / 60));
        $this->info("Date passes: {$passCount} (each ~" . self::DATE_BATCH_MONTHS . " months)");
        $this->info("Rooms with slots: {$roomsWithSlots}");
        $this->info("Rooms empty: {$roomsEmpty}");
        $this->info("Total slots found: {$totalSlots}");
        if (!$dryRun) {
            $this->info("DB created: {$totalCreated}");
            $this->info("DB deleted: {$totalDeleted}");
        }

        return Command::SUCCESS;
    }

    /**
     * Extract available time slots from the API day array.
     */
    private function extractSlots(array $days): array
    {
        $slots = [];

        foreach ($days as $day) {
            if (!($day['HasAvailable'] ?? false)) {
                continue;
            }

            $date = sprintf('%04d-%02d-%02d', $day['Year'] ?? 0, $day['Month'] ?? 0, $day['Day'] ?? 0);
            $timeSlots = $day['AvailabilityTimeSlotList'] ?? [];

            foreach ($timeSlots as $slot) {
                if (($slot['Quantity'] ?? 0) == 0) {
                    continue;
                }

                // Match time with either colon (:) or period (.) as separator
                preg_match('/\b(\d{1,2})[:\.](\d{2})\b/', $slot['Name'] ?? '', $m);
                if (!empty($m[1]) && !empty($m[2])) {
                    $time = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
                    $slots[] = ['date' => $date, 'time' => $time];
                }
            }
        }

        return $slots;
    }

    /**
     * Check if any day in the response has available slots.
     */
    private function hasAnyAvailableSlots(array|null $days): bool
    {
        if (!is_array($days) || empty($days)) {
            return false;
        }

        foreach ($days as $day) {
            if (!($day['HasAvailable'] ?? false)) {
                continue;
            }

            $timeSlots = $day['AvailabilityTimeSlotList'] ?? [];
            foreach ($timeSlots as $slot) {
                if (($slot['Quantity'] ?? 0) > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Sync availabilities for a room — creates new, deletes removed.
     */
    private function syncRoomAvailabilities(Room $room, array $newSlots): array
    {
        $created = 0;
        $deleted = 0;

        // Build lookup
        $newKeys = [];
        foreach ($newSlots as $slot) {
            $newKeys[$slot['date'] . '|' . $slot['time']] = $slot;
        }

        // Date range from slots
        $dates = array_column($newSlots, 'date');
        $minDate = min($dates);
        $maxDate = max($dates);

        // Existing records in range
        $existing = RoomAvailability::where('room_id', $room->id)
            ->whereBetween('available_date', [$minDate, $maxDate])
            ->get();

        $existingKeys = [];
        foreach ($existing as $record) {
            $key = $record->available_date->format('Y-m-d') . '|' . $record->available_time;
            $existingKeys[$key] = $record;
        }

        // Delete removed
        foreach ($existingKeys as $key => $record) {
            if (!isset($newKeys[$key])) {
                $record->delete();
                $deleted++;
            }
        }

        // Create new
        $toCreate = [];
        foreach ($newKeys as $key => $slot) {
            if (!isset($existingKeys[$key])) {
                $toCreate[] = [
                    'room_id'        => $room->id,
                    'available_date' => $slot['date'],
                    'available_time' => $slot['time'],
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ];
            }
        }

        if (!empty($toCreate)) {
            foreach (array_chunk($toCreate, 100) as $chunk) {
                RoomAvailability::insert($chunk);
            }
            $created = count($toCreate);
        }

        return ['created' => $created, 'deleted' => $deleted];
    }
}

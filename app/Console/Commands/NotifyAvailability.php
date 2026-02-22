<?php

namespace App\Console\Commands;

use App\Mail\NewSlotsAvailableMail;
use App\Models\Reminder;
use App\Models\Room;
use App\Models\RoomAvailability;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Process\Process;

class NotifyAvailability extends Command
{
    protected $signature = 'reminders:notify-availability
        {--dry-run : Show what would be sent without actually sending emails or syncing}
        {--show-logs : Display detailed processing info and Node output}
        {--use-slow : Use the original stealth script instead of the fast one}
        {--delay-ms=1500 : Milliseconds between API calls in fast mode (default: 1500)}';

    protected $description = 'Scrape EscapeAll for rooms with reminders, detect new availability slots, and email users';

    public function handle(): int
    {
        $dryRun   = (bool) $this->option('dry-run');
        $showLogs = (bool) $this->option('show-logs');
        $useSlow  = (bool) $this->option('use-slow');
        $delayMs  = (int) $this->option('delay-ms');

        $today       = now()->startOfDay();
        $threeMonths = now()->addMonths(3)->endOfDay();

        if ($dryRun) {
            $this->warn('[notify] DRY-RUN mode — no emails will be sent, no DB changes');
        }

        $this->line('[notify] Checking for new availability slots...');
        $this->line("[notify] Date range: {$today->toDateString()} → {$threeMonths->toDateString()}");

        // ── 1) Get distinct rooms that have at least one reminder ──
        $roomIds = Reminder::distinct()->pluck('room_id')->toArray();

        if (empty($roomIds)) {
            $this->info('[notify] No rooms have reminders. Nothing to do.');
            return Command::SUCCESS;
        }

        $rooms = Room::with('company')
            ->whereIn('id', $roomIds)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->get();

        if ($rooms->isEmpty()) {
            $this->info('[notify] No rooms with valid external_id have reminders.');
            return Command::SUCCESS;
        }

        $this->info("[notify] Found {$rooms->count()} room(s) with reminders");
        $this->line('[notify] Mode: ' . ($useSlow ? 'SLOW (full stealth)' : 'FAST (single browser)'));
        $this->newLine();

        // ── 2) Scrape availability ──
        if ($useSlow) {
            $allResults = $this->scrapeAllRoomsSlow($rooms, $today, $threeMonths, $showLogs);
        } else {
            $allResults = $this->scrapeAllRoomsFast($rooms, $today, $threeMonths, $showLogs, $delayMs);
        }

        if ($allResults === null) {
            $this->error('[notify] Scraping failed completely.');
            return Command::FAILURE;
        }

        // ── 3) For each room: compare, notify, sync ──
        $totalEmails   = 0;
        $totalNewSlots = 0;

        foreach ($rooms as $room) {
            $scrapedSlots = $allResults[$room->external_id] ?? [];

            $this->line("<fg=cyan>{$room->title}</> — " . count($scrapedSlots) . ' slot(s) scraped');

            // Get existing slots from DB (the previous snapshot)
            $existingKeys = RoomAvailability::where('room_id', $room->id)
                ->whereBetween('available_date', [$today->toDateString(), $threeMonths->toDateString()])
                ->get()
                ->mapWithKeys(fn($a) => [
                    $a->available_date->format('Y-m-d') . '|' . substr($a->available_time, 0, 5) => true,
                ])
                ->toArray();

            // Find NEW slots
            $newSlots = [];
            foreach ($scrapedSlots as $slot) {
                $key = $slot['date'] . '|' . $slot['time'];
                if (!isset($existingKeys[$key])) {
                    $newSlots[] = $slot;
                }
            }

            if (empty($newSlots)) {
                $this->line("  ○ No new slots");
            } else {
                $this->info("  <fg=green>✓ " . count($newSlots) . " NEW slot(s)!</>");
                $totalNewSlots += count($newSlots);

                if ($showLogs) {
                    foreach (array_slice($newSlots, 0, 5) as $s) {
                        $this->line("    📅 {$s['date']} 🕐 {$s['time']}");
                    }
                    if (count($newSlots) > 5) {
                        $this->line("    ... and " . (count($newSlots) - 5) . " more");
                    }
                }

                // Notify ALL users with a reminder on this room
                $reminders = Reminder::with('user')->where('room_id', $room->id)->get();

                foreach ($reminders as $reminder) {
                    $user = $reminder->user;
                    if (!$user) continue;

                    $this->line("  → Emailing {$user->email}");

                    if (!$dryRun) {
                        try {
                            Mail::to($user->email)->send(
                                new NewSlotsAvailableMail($room, $newSlots, $user)
                            );
                            $totalEmails++;
                        } catch (\Throwable $e) {
                            $this->error("  ✗ Failed: {$e->getMessage()}");
                        }
                    } else {
                        $totalEmails++;
                    }
                }
            }

            // Sync fresh data into room_availabilities
            if (!$dryRun) {
                $syncResult = $this->syncRoomAvailabilities($room, $scrapedSlots, $today, $threeMonths);
                if ($syncResult['created'] > 0 || $syncResult['deleted'] > 0) {
                    $this->line("  DB: <fg=green>+{$syncResult['created']}</> / <fg=red>-{$syncResult['deleted']}</>");
                }
            }
        }

        $this->newLine();
        $this->info("[notify] Done. New slots: {$totalNewSlots}, Emails: {$totalEmails}");

        return Command::SUCCESS;
    }

    // ─────────────────────────────────────────────
    // FAST MODE: single browser, all rooms at once
    // ─────────────────────────────────────────────

    private function scrapeAllRoomsFast($rooms, $today, $threeMonths, bool $showLogs, int $delayMs): ?array
    {
        $script = base_path('node/scripts/fetch-availability-fast.js');
        if (!is_file($script)) {
            $this->error("Fast script not found at: {$script}");
            return null;
        }

        // Collect all 2-month batch ranges that cover today → 3 months
        $batches = [];
        $currentFrom = new \DateTime($today->toDateString());
        $limit = new \DateTime($threeMonths->toDateString());

        while ($currentFrom <= $limit) {
            $batchEnd = min(
                (clone $currentFrom)->modify('+2 months -1 day'),
                clone $limit
            );
            $batches[] = [
                'from' => $currentFrom->format('Y-m-d'),
                'until' => $batchEnd->format('Y-m-d'),
            ];
            $currentFrom = (clone $batchEnd)->modify('+1 day');
        }

        $serviceIds = $rooms->pluck('external_id')->join(',');
        $allResults = [];

        foreach ($batches as $bi => $batch) {
            $this->line("[notify] Batch " . ($bi + 1) . "/" . count($batches) . ": {$batch['from']} → {$batch['until']}");

            $args = [
                'node',
                $script,
                "from={$batch['from']}",
                "until={$batch['until']}",
                "serviceIds={$serviceIds}",
                'language=el',
                'bookedBy=1',
                'noGifts=false',
                "delayMs={$delayMs}",
            ];

            $timeout = 30 + ($rooms->count() * 10); // ~10s per room + 30s overhead
            $process = new Process($args, base_path(), ['NODE_PATH' => base_path('node/automation/node_modules')]);
            $process->setTimeout($timeout);
            $process->run();

            if ($showLogs) {
                $stderr = $process->getErrorOutput();
                if ($stderr) {
                    foreach (explode("\n", trim($stderr)) as $line) {
                        $this->line("  <fg=gray>[node] {$line}</>");
                    }
                }
            }

            if (!$process->isSuccessful()) {
                $this->error("[notify] Batch failed: " . $process->getErrorOutput());
                return null;
            }

            $output = trim($process->getOutput());
            $json = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("[notify] Invalid JSON output from fast script");
                if ($showLogs) {
                    $this->line("  Output preview: " . substr($output, 0, 300));
                }
                return null;
            }

            // Merge batch results — each key is a serviceId with an array of days
            foreach ($json as $sid => $days) {
                if (!isset($allResults[$sid])) {
                    $allResults[$sid] = [];
                }

                // Extract available slots from the raw day data
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

                        preg_match('/\b(\d{1,2}:\d{2})\b/', $slot['Name'] ?? '', $m);
                        if (!empty($m[1])) {
                            $timeParts = explode(':', $m[1]);
                            $time = str_pad($timeParts[0], 2, '0', STR_PAD_LEFT) . ':' . $timeParts[1];
                            $allResults[$sid][] = ['date' => $date, 'time' => $time];
                        }
                    }
                }
            }

            // Small delay between batches
            if ($bi < count($batches) - 1) {
                sleep(3);
            }
        }

        return $allResults;
    }

    // ─────────────────────────────────────────────
    // SLOW MODE: original stealth script, one room at a time
    // ─────────────────────────────────────────────

    private function scrapeAllRoomsSlow($rooms, $today, $threeMonths, bool $showLogs): ?array
    {
        $script = base_path('node/scripts/fetch-availability.js');
        if (!is_file($script)) {
            $this->error("Stealth script not found at: {$script}");
            return null;
        }

        $allResults = [];

        foreach ($rooms as $index => $room) {
            $this->line("  [{$room->title}] Scraping with stealth mode...");

            $currentFrom = new \DateTime($today->toDateString());
            $limit = new \DateTime($threeMonths->toDateString());
            $roomSlots = [];
            $batchCount = 0;

            while ($batchCount < 2 && $currentFrom <= $limit) {
                $batchEnd = min(
                    (clone $currentFrom)->modify('+2 months -1 day'),
                    clone $limit
                );
                $batchCount++;

                $args = [
                    'node',
                    $script,
                    'from=' . $currentFrom->format('Y-m-d'),
                    'until=' . $batchEnd->format('Y-m-d'),
                    "serviceId={$room->external_id}",
                    'bookedBy=1',
                    'language=el',
                    'noGifts=false',
                    'waitMs=3000',
                    'maxRetries=2',
                ];

                $process = new Process($args, base_path(), ['NODE_PATH' => base_path('node/automation/node_modules')]);
                $process->setTimeout(120);
                $process->run();

                if (!$process->isSuccessful()) {
                    if ($showLogs) {
                        $this->warn("    Batch failed: " . $process->getErrorOutput());
                    }
                    break;
                }

                $output = trim($process->getOutput());
                $start = strpos($output, '[');
                $end = strrpos($output, ']');

                if ($start === false || $end === false) {
                    break;
                }

                $jsonText = substr($output, $start, $end - $start + 1);
                $json = json_decode($jsonText, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    $currentFrom = (clone $batchEnd)->modify('+1 day');
                    continue;
                }

                // Extract available slots
                foreach ($json as $day) {
                    if (!($day['HasAvailable'] ?? false)) {
                        continue;
                    }

                    $date = sprintf('%04d-%02d-%02d', $day['Year'] ?? 0, $day['Month'] ?? 0, $day['Day'] ?? 0);
                    foreach ($day['AvailabilityTimeSlotList'] ?? [] as $slot) {
                        if (($slot['Quantity'] ?? 0) == 0) {
                            continue;
                        }

                        preg_match('/\b(\d{1,2}:\d{2})\b/', $slot['Name'] ?? '', $m);
                        if (!empty($m[1])) {
                            $timeParts = explode(':', $m[1]);
                            $time = str_pad($timeParts[0], 2, '0', STR_PAD_LEFT) . ':' . $timeParts[1];
                            $roomSlots[] = ['date' => $date, 'time' => $time];
                        }
                    }
                }

                $currentFrom = (clone $batchEnd)->modify('+1 day');

                if ($batchCount < 2 && $currentFrom <= $limit) {
                    sleep(10);
                }
            }

            $allResults[$room->external_id] = $roomSlots;

            // Delay between rooms
            if ($index < $rooms->count() - 1) {
                $wait = 15 + random_int(0, 10);
                $this->line("    <fg=gray>Waiting {$wait}s...</>");
                sleep($wait);
            }
        }

        return $allResults;
    }

    // ─────────────────────────────────────────────
    // DB sync helper
    // ─────────────────────────────────────────────

    private function syncRoomAvailabilities(Room $room, array $newSlots, $fromDate, $untilDate): array
    {
        $created = 0;
        $deleted = 0;

        $newKeys = [];
        foreach ($newSlots as $slot) {
            $key = $slot['date'] . '|' . $slot['time'];
            $newKeys[$key] = $slot;
        }

        $existing = RoomAvailability::where('room_id', $room->id)
            ->whereBetween('available_date', [$fromDate->toDateString(), $untilDate->toDateString()])
            ->get();

        $existingKeys = [];
        foreach ($existing as $record) {
            $key = $record->available_date->format('Y-m-d') . '|' . substr($record->available_time, 0, 5);
            $existingKeys[$key] = $record;
        }

        // Delete records no longer present
        foreach ($existingKeys as $key => $record) {
            if (!isset($newKeys[$key])) {
                $record->delete();
                $deleted++;
            }
        }

        // Create new records
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

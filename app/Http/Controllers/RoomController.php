<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomAvailability;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class RoomController extends Controller
{
    /**
     * Display the specified room.
     */
    public function show(Room $room)
    {
        // Eager-load related municipality for display
        $room->load('municipality:id,name', 'company');

        return view('rooms.show', [
            'room' => $room,
        ]);
    }

    /**
     * Return availability slots for a room, grouped by date.
     * GET /rooms/{room}/availability?month=2026-03
     */
    public function availability(Room $room, Request $request)
    {
        $month = $request->query('month', now()->format('Y-m'));

        // Parse to first/last day of month
        try {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $end   = (clone $start)->endOfMonth();
        } catch (\Exception $e) {
            return response()->json([], 400);
        }

        $slots = RoomAvailability::where('room_id', $room->id)
            ->whereBetween('available_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('available_date')
            ->orderBy('available_time')
            ->get()
            ->groupBy(fn($s) => $s->available_date->format('Y-m-d'))
            ->map(fn($group) => $group->map(fn($s) => substr($s->available_time, 0, 5))->values())
            ->toArray();

        return response()->json($slots);
    }

    /**
     * Live-scrape EscapeAll for this room and stream progress events.
     * POST /rooms/{room}/refresh-availability
     */
    public function refreshAvailability(Room $room)
    {
        if (empty($room->external_id)) {
            return response()->json(['error' => 'Room has no external ID'], 422);
        }

        return new StreamedResponse(function () use ($room) {
            // Disable output buffering for real-time streaming
            while (ob_get_level()) {
                ob_end_clean();
            }

            // Release session lock to allow other requests
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            // Ignore user abort - let the process complete
            ignore_user_abort(true);
            set_time_limit(300); // 5 minutes max

            $this->sendEvent('progress', ['step' => 'init', 'progress' => 5, 'message' => 'Preparing...']);

            $script = base_path('node/scripts/fetch-availability-fast.js');
            if (!is_file($script)) {
                $this->sendEvent('error', ['message' => 'Scraping script not found']);
                return;
            }

            $today = now()->startOfDay();
            // End of year, but minimum 3 months from today
            $endOfYear = now()->endOfYear();
            $threeMonths = now()->addMonths(3)->endOfDay();
            $until = $threeMonths->gt($endOfYear) ? $threeMonths : $endOfYear;

            // Build 2-month batches
            $batches = [];
            $currentFrom = new \DateTime($today->toDateString());
            $limit = new \DateTime($until->toDateString());
            while ($currentFrom <= $limit) {
                $batchEnd = min(
                    (clone $currentFrom)->modify('+2 months -1 day'),
                    clone $limit
                );
                $batches[] = [
                    'from'  => $currentFrom->format('Y-m-d'),
                    'until' => $batchEnd->format('Y-m-d'),
                ];
                $currentFrom = (clone $batchEnd)->modify('+1 day');
            }

            $totalBatches = count($batches);
            $allSlots = [];

            $this->sendEvent('progress', [
                'step'     => 'launching',
                'progress' => 10,
                'message'  => 'Launching browser...',
                'batches'  => $totalBatches,
            ]);

            // Run ALL batches in one fast script call (it handles single serviceId fine)
            foreach ($batches as $bi => $batch) {
                $batchNum = $bi + 1;
                $pct = 10 + intval(($batchNum / $totalBatches) * 70); // 10% → 80%

                $this->sendEvent('progress', [
                    'step'     => 'fetching',
                    'progress' => min($pct, 80),
                    'message'  => "Fetching batch {$batchNum}/{$totalBatches} ({$batch['from']} → {$batch['until']})...",
                ]);

                $args = [
                    'node', $script,
                    "from={$batch['from']}",
                    "until={$batch['until']}",
                    "serviceIds={$room->external_id}",
                    'language=el', 'bookedBy=1', 'noGifts=false',
                    'delayMs=1500',
                ];

                $process = new Process($args, base_path(), ['NODE_PATH' => base_path('node/automation/node_modules')]);
                $process->setTimeout(90);
                $process->run();

                // Always capture output for debugging (before checking success)
                $output = trim($process->getOutput());
                $errorOutput = trim($process->getErrorOutput());
                $json = json_decode($output, true);

                // Store raw response for debugging purposes - ALWAYS
                $debugDir = storage_path('app/sync-data/availability-debug');
                if (!is_dir($debugDir)) {
                    mkdir($debugDir, 0775, true);
                }
                $debugFile = $debugDir . '/' . $room->id . '_' . preg_replace('/[^a-zA-Z0-9\-]/', '', $room->external_id) . '.json';
                $debugData = [
                    'room_id' => $room->id,
                    'room_title' => $room->title,
                    'external_id' => $room->external_id,
                    'batch' => $batchNum,
                    'from' => $batch['from'],
                    'until' => $batch['until'],
                    'timestamp' => now()->toIso8601String(),
                    'process_successful' => $process->isSuccessful(),
                    'exit_code' => $process->getExitCode(),
                    'raw_output' => $output,
                    'error_output' => $errorOutput,
                    'json_decoded' => $json,
                    'json_error' => json_last_error() !== JSON_ERROR_NONE ? json_last_error_msg() : null,
                ];

                // Append to debug file (one entry per batch)
                $existingDebug = [];
                if (file_exists($debugFile)) {
                    $existingContent = file_get_contents($debugFile);
                    $existingDebug = json_decode($existingContent, true) ?: [];
                }
                if (!isset($existingDebug['batches'])) {
                    $existingDebug['batches'] = [];
                }
                $existingDebug['batches'][] = $debugData;
                $existingDebug['last_updated'] = now()->toIso8601String();
                file_put_contents($debugFile, json_encode($existingDebug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                if (!$process->isSuccessful()) {
                    $this->sendEvent('error', [
                        'message'  => "Batch {$batchNum} failed",
                        'progress' => $pct,
                    ]);
                    return;
                }


                if (json_last_error() !== JSON_ERROR_NONE || !is_array($json)) {
                    continue;
                }

                // Extract slots from the response (keyed by serviceId)
                $days = $json[$room->external_id] ?? [];
                foreach ($days as $day) {
                    if (!($day['HasAvailable'] ?? false)) continue;

                    $date = sprintf('%04d-%02d-%02d', $day['Year'] ?? 0, $day['Month'] ?? 0, $day['Day'] ?? 0);

                    foreach ($day['AvailabilityTimeSlotList'] ?? [] as $slot) {
                        if (($slot['Quantity'] ?? 0) == 0) continue;

                        // Match time with either colon (:) or period (.) as separator
                        preg_match('/\b(\d{1,2})[:\.](\d{2})\b/', $slot['Name'] ?? '', $m);
                        if (!empty($m[1]) && !empty($m[2])) {
                            $time = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
                            $allSlots[] = ['date' => $date, 'time' => $time];
                        }
                    }
                }
            }

            // ── Sync to DB ──
            $this->sendEvent('progress', [
                'step'     => 'syncing',
                'progress' => 85,
                'message'  => 'Syncing ' . count($allSlots) . ' slots to database...',
            ]);

            $created = 0;
            $deleted = 0;

            $newKeys = [];
            foreach ($allSlots as $slot) {
                $key = $slot['date'] . '|' . $slot['time'];
                $newKeys[$key] = $slot;
            }

            $existing = RoomAvailability::where('room_id', $room->id)
                ->whereBetween('available_date', [$today->toDateString(), $until->toDateString()])
                ->get();

            $existingKeys = [];
            foreach ($existing as $record) {
                $key = $record->available_date->format('Y-m-d') . '|' . substr($record->available_time, 0, 5);
                $existingKeys[$key] = $record;
            }

            foreach ($existingKeys as $key => $record) {
                if (!isset($newKeys[$key])) {
                    $record->delete();
                    $deleted++;
                }
            }

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

            $this->sendEvent('progress', [
                'step'     => 'done',
                'progress' => 100,
                'message'  => 'Complete!',
                'total'    => count($allSlots),
                'created'  => $created,
                'deleted'  => $deleted,
            ]);

        }, 200, [
            'Content-Type'  => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection'    => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function sendEvent(string $event, array $data): void
    {
        echo "event: {$event}\n";
        echo 'data: ' . json_encode($data) . "\n\n";

        if (ob_get_level()) ob_flush();
        flush();
    }
}


<?php

namespace App\Http\Controllers;

use App\Mail\NewSlotsAvailableMail;
use App\Models\Company;
use App\Models\Municipality;
use App\Models\Reminder;
use App\Models\Room;
use App\Models\RoomAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class WebhookController extends Controller
{
    /**
     * Receive companies + areas JSON from GitHub Actions and upsert into DB.
     *
     * Expected payload: { "companies": [...], "areas": [...] }
     */
    public function syncCompanies(Request $request): JsonResponse
    {
        $data = $request->validate([
            'companies' => 'required|array',
            'areas'     => 'required|array',
        ]);

        $companies = $data['companies'];
        $areas     = $data['areas'];

        // ── 1) Upsert municipalities from areas ──
        $aCreated = 0;
        $aUpdated = 0;
        $aFailed  = 0;

        foreach ($areas as $a) {
            try {
                $model = Municipality::updateOrCreate(
                    ['external_id' => (string) ($a['external_id'] ?? ($a['id'] ?? ''))],
                    ['name' => (string) ($a['name'] ?? ($a['label'] ?? ''))]
                );
                $model->wasRecentlyCreated ? $aCreated++ : $aUpdated++;
            } catch (\Throwable $e) {
                $aFailed++;
            }
        }

        // Build lookup for fast municipality existence checks
        $knownMunicipalities    = Municipality::query()->pluck('external_id')->flip()->all();
        $autoCreatedMunicipalities = 0;

        // ── 2) Upsert companies ──
        $cCreated = 0;
        $cUpdated = 0;
        $cFailed  = 0;

        foreach ($companies as $c) {
            try {
                $munExtId = (string) ($c['municipality_external_id'] ?? '');

                // Auto-create municipality if it doesn't exist yet
                if ($munExtId !== '' && ! isset($knownMunicipalities[$munExtId])) {
                    Municipality::create([
                        'external_id' => $munExtId,
                        'name'        => 'Unknown (' . $munExtId . ')',
                    ]);
                    $knownMunicipalities[$munExtId] = true;
                    $autoCreatedMunicipalities++;
                }

                $model = Company::updateOrCreate(
                    ['external_id' => (string) ($c['external_id'] ?? '')],
                    [
                        'name'                     => (string) ($c['name'] ?? ''),
                        'logo_url'                 => (string) ($c['logo_url'] ?? ''),
                        'latitude'                 => $c['latitude'] ?? null,
                        'longitude'                => $c['longitude'] ?? null,
                        'address'                  => (string) ($c['address'] ?? ''),
                        'full_address'             => (string) ($c['full_address'] ?? ''),
                        'municipality_external_id' => $munExtId ?: null,
                    ]
                );
                $model->wasRecentlyCreated ? $cCreated++ : $cUpdated++;
            } catch (\Throwable $e) {
                $cFailed++;
            }
        }

        return response()->json([
            'status' => 'ok',
            'areas'  => [
                'created' => $aCreated,
                'updated' => $aUpdated,
                'failed'  => $aFailed,
            ],
            'companies' => [
                'created'                  => $cCreated,
                'updated'                  => $cUpdated,
                'failed'                   => $cFailed,
                'auto_created_municipalities' => $autoCreatedMunicipalities,
            ],
        ]);
    }

    /**
     * Receive rooms listing + companies JS data from GitHub Actions and upsert into DB.
     *
     * Expected payload: { "rooms": [...], "companies": [...] }
     */
    public function syncRooms(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rooms'     => 'required|array',
            'companies' => 'present|array',
        ]);

        $rooms      = $data['rooms'];
        $companiesJs = $data['companies'];

        // ── 1) Auto-upsert companies from JS variable ──
        $jsUpCreated = 0;
        $jsUpUpdated = 0;

        foreach ($companiesJs as $jc) {
            $cid  = $jc['CompanyId'] ?? '';
            $name = $jc['DisplayName'] ?? '';
            if ($cid === '' || $name === '') {
                continue;
            }

            try {
                $existing    = Company::where('name', $name)->first();
                $companyData = array_filter([
                    'name'                     => $name,
                    'logo_url'                 => $jc['LogoUrl'] ?? '',
                    'latitude'                 => $jc['Latitude'] ?? null,
                    'longitude'                => $jc['Longitude'] ?? null,
                    'address'                  => $jc['Address'] ?? '',
                    'full_address'             => $jc['FullAddress'] ?? '',
                    'municipality_external_id' => $jc['Municipalityid'] ?? null,
                ], fn ($v) => $v !== null && $v !== '');

                if ($existing) {
                    $existing->update($companyData);
                    $jsUpUpdated++;
                } else {
                    $model = Company::updateOrCreate(['external_id' => $cid], $companyData);
                    $model->wasRecentlyCreated ? $jsUpCreated++ : $jsUpUpdated++;
                }
            } catch (\Throwable) {
                // silently skip
            }
        }

        // ── 2) Build room rows ──
        $rows = [];
        foreach ($rooms as $item) {
            if (! is_array($item)) {
                continue;
            }
            $extId = (string) ($item['external_id'] ?? '');
            $title = (string) ($item['title'] ?? '');
            if ($extId === '' || $title === '') {
                continue;
            }

            $slug = $item['slug'] ?? '';

            $durationMinutes = $item['duration_minutes'] ?? null;
            if ($durationMinutes !== null && $durationMinutes > 0 && $durationMinutes <= 5) {
                $durationMinutes = $durationMinutes * 60;
            }

            $rows[] = [
                'external_id'         => $extId,
                'title'               => $title,
                'slug'                => $slug ?: null,
                'label'               => $title . ' - ' . ($item['company_name'] ?? ''),
                'provider'            => $item['company_name'] ?? '',
                'company_external_id' => $item['company_external_id'] ?? '',
                'short_description'   => $item['short_description'] ?? null,
                'rating'              => $item['rating'] ?? null,
                'reviews_count'       => $item['reviews_count'] ?? null,
                'duration_minutes'    => $durationMinutes,
                'min_players'         => $item['min_players'] ?? null,
                'max_players'         => $item['max_players'] ?? null,
                'escape_rate'         => $item['escape_rate'] ?? null,
                'image_url'           => $item['image_url'] ?? null,
                'categories'          => $item['categories'] ?? [],
            ];
        }

        if (empty($rows)) {
            return response()->json([
                'status' => 'ok',
                'message' => 'No valid room rows to process',
                'rooms' => ['created' => 0, 'updated' => 0, 'failed' => 0],
            ]);
        }

        // ── 3) Resolve company_id ──
        $companyExtIds    = array_values(array_unique(array_filter(array_map(fn ($r) => $r['company_external_id'] ?? '', $rows))));
        $companiesById    = Company::whereIn('external_id', $companyExtIds)->pluck('id', 'external_id')->all();
        $companyNames     = array_values(array_unique(array_filter(array_map(fn ($r) => $r['provider'] ?? '', $rows))));
        $companiesByName  = Company::whereIn('name', $companyNames)->pluck('id', 'name')->all();

        // ── 4) Upsert rooms ──
        $created      = 0;
        $updated      = 0;
        $upsertFailed = 0;

        foreach ($rows as $r) {
            try {
                $companyId = $companiesById[$r['company_external_id']]
                    ?? $companiesByName[$r['provider']]
                    ?? null;

                $updateData = [
                    'label'      => $r['label'],
                    'title'      => $r['title'],
                    'provider'   => $r['provider'],
                    'slug'       => $r['slug'],
                    'company_id' => $companyId,
                ];

                foreach (['short_description', 'image_url'] as $f) {
                    if (! empty($r[$f])) {
                        $updateData[$f] = $r[$f];
                    }
                }
                foreach (['rating', 'reviews_count', 'duration_minutes', 'min_players', 'max_players', 'escape_rate'] as $f) {
                    if ($r[$f] !== null) {
                        $updateData[$f] = $r[$f];
                    }
                }
                if (! empty($r['categories'])) {
                    $updateData['categories'] = $r['categories'];
                }

                $room = Room::updateOrCreate(['external_id' => $r['external_id']], $updateData);
                $room->wasRecentlyCreated ? $created++ : $updated++;
            } catch (\Throwable) {
                $upsertFailed++;
            }
        }

        return response()->json([
            'status'    => 'ok',
            'rooms'     => [
                'created' => $created,
                'updated' => $updated,
                'failed'  => $upsertFailed,
            ],
            'companies' => [
                'created' => $jsUpCreated,
                'updated' => $jsUpUpdated,
            ],
        ]);
    }

    /**
     * Receive room availability data from GitHub Actions and sync to DB.
     *
     * Expected payload: { "results": { "extId": [ {...day...}, ... ], ... }, "from": "YYYY-MM-DD" }
     */
    public function syncAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'results' => 'required|array',
            'from'    => 'required|date',
        ]);

        $results  = $data['results'];
        $fromDate = $data['from'];

        // Load all rooms with matching external IDs
        $extIds       = array_keys($results);
        $roomsByExtId = Room::whereIn('external_id', $extIds)->get()->keyBy('external_id');

        $totalSlots   = 0;
        $totalCreated = 0;
        $totalDeleted = 0;
        $roomsWithSlots = 0;
        $roomsEmpty     = 0;

        foreach ($extIds as $extId) {
            $room = $roomsByExtId[$extId] ?? null;
            if (! $room) {
                continue;
            }

            $days  = $results[$extId];
            $slots = $this->extractSlots(is_array($days) ? $days : []);

            if (empty($slots)) {
                $roomsEmpty++;
                // Delete ALL availabilities since the scrape returned nothing
                $deleted = RoomAvailability::where('room_id', $room->id)
                    ->delete();
                $totalDeleted += $deleted;

                // Mark refresh as completed even for empty rooms
                Cache::put("room:{$room->id}:refresh_completed", now()->timestamp, 600);
                Cache::put("room:{$room->id}:refresh_result", [
                    'slots' => 0, 'created' => 0, 'deleted' => $deleted,
                ], 600);
                continue;
            }

            // Deduplicate slots
            $uniqueSlots = [];
            foreach ($slots as $s) {
                $key               = $s['date'] . '|' . $s['time'];
                $uniqueSlots[$key] = $s;
            }
            $slots = array_values($uniqueSlots);

            $roomsWithSlots++;
            $totalSlots += count($slots);

            $syncResult    = $this->syncRoomAvailabilities($room, $slots);
            $totalCreated += $syncResult['created'];
            $totalDeleted += $syncResult['deleted'];

            // Mark refresh as completed for this room (used by frontend polling)
            Cache::put("room:{$room->id}:refresh_completed", now()->timestamp, 600);
            Cache::put("room:{$room->id}:refresh_result", [
                'slots' => count($slots),
                'created' => $syncResult['created'],
                'deleted' => $syncResult['deleted'],
            ], 600);
        }

        return response()->json([
            'status'          => 'ok',
            'rooms_with_slots' => $roomsWithSlots,
            'rooms_empty'     => $roomsEmpty,
            'total_slots'     => $totalSlots,
            'created'         => $totalCreated,
            'deleted'         => $totalDeleted,
        ]);
    }

    /**
     * Return all room external IDs (used by the availability orchestrator in GitHub Actions).
     */
    public function roomIds(): JsonResponse
    {
        $ids = Room::whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->pluck('external_id')
            ->values();

        return response()->json(['room_ids' => $ids]);
    }

    /**
     * Return external IDs of rooms that have at least one active reminder.
     * Used by the notify-availability GitHub Actions workflow.
     */
    public function reminderRoomIds(): JsonResponse
    {
        $roomIds = Reminder::distinct()->pluck('room_id');

        $extIds = Room::whereIn('id', $roomIds)
            ->whereNotNull('external_id')
            ->where('external_id', '!=', '')
            ->pluck('external_id')
            ->values();

        return response()->json(['room_ids' => $extIds]);
    }

    /**
     * Receive scraped availability for rooms with reminders.
     * Compares against DB to find NEW slots, emails users, then syncs DB.
     *
     * Expected payload: { "results": { "extId": [ {...day...}, ... ], ... } }
     */
    public function notifyAvailability(Request $request): JsonResponse
    {
        $data = $request->validate([
            'results' => 'required|array',
        ]);

        $results = $data['results'];
        $today       = now()->startOfDay();
        $threeMonths = now()->addMonths(3)->endOfDay();

        // Load rooms with matching external IDs
        $extIds       = array_keys($results);
        $roomsByExtId = Room::with('company')
            ->whereIn('external_id', $extIds)
            ->get()
            ->keyBy('external_id');

        $totalEmails   = 0;
        $totalNewSlots = 0;
        $roomsProcessed = 0;

        foreach ($extIds as $extId) {
            $room = $roomsByExtId[$extId] ?? null;
            if (! $room) {
                continue;
            }

            $roomsProcessed++;
            $days  = $results[$extId];
            $scrapedSlots = $this->extractSlots(is_array($days) ? $days : []);

            // ── Get existing slots from DB (the previous snapshot) ──
            $existingKeys = RoomAvailability::where('room_id', $room->id)
                ->whereBetween('available_date', [$today->toDateString(), $threeMonths->toDateString()])
                ->get()
                ->mapWithKeys(fn ($a) => [
                    $a->available_date->format('Y-m-d') . '|' . substr($a->available_time, 0, 5) => true,
                ])
                ->toArray();

            // ── Find NEW slots ──
            $newSlots = [];
            foreach ($scrapedSlots as $slot) {
                $key = $slot['date'] . '|' . $slot['time'];
                if (! isset($existingKeys[$key])) {
                    $newSlots[] = $slot;
                }
            }

            $totalNewSlots += count($newSlots);

            // ── Notify users with reminders on this room ──
            if (! empty($newSlots)) {
                $reminders = Reminder::with('user')->where('room_id', $room->id)->get();

                foreach ($reminders as $reminder) {
                    $user = $reminder->user;
                    if (! $user) {
                        continue;
                    }

                    try {
                        Mail::to($user->email)->send(
                            new NewSlotsAvailableMail($room, $newSlots, $user)
                        );
                        $totalEmails++;
                    } catch (\Throwable $e) {
                        // Log but don't fail the whole request
                        report($e);
                    }
                }
            }

            // ── Sync fresh data into room_availabilities ──
            if (! empty($scrapedSlots)) {
                $this->syncRoomAvailabilities($room, $scrapedSlots);
            } else {
                // Delete future availabilities since nothing is available
                RoomAvailability::where('room_id', $room->id)
                    ->where('available_date', '>=', $today->toDateString())
                    ->delete();
            }
        }

        return response()->json([
            'status'          => 'ok',
            'rooms_processed' => $roomsProcessed,
            'new_slots'       => $totalNewSlots,
            'emails_sent'     => $totalEmails,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Private helpers (extracted from the console commands)
    // ──────────────────────────────────────────────────────────────

    /**
     * Extract available time slots from the API day array.
     */
    private function extractSlots(array $days): array
    {
        $slots = [];

        foreach ($days as $day) {
            if (! ($day['HasAvailable'] ?? false)) {
                continue;
            }

            $date      = sprintf('%04d-%02d-%02d', $day['Year'] ?? 0, $day['Month'] ?? 0, $day['Day'] ?? 0);
            $timeSlots = $day['AvailabilityTimeSlotList'] ?? [];

            foreach ($timeSlots as $slot) {
                if (($slot['Quantity'] ?? 0) == 0) {
                    continue;
                }

                preg_match('/\b(\d{1,2})[:\.](\d{2})\b/', $slot['Name'] ?? '', $m);
                if (! empty($m[1]) && ! empty($m[2])) {
                    $time    = str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2];
                    $slots[] = ['date' => $date, 'time' => $time];
                }
            }
        }

        return $slots;
    }

    /**
     * Sync availabilities for a room — deletes ALL existing and replaces with fresh data.
     */
    private function syncRoomAvailabilities(Room $room, array $newSlots): array
    {
        // Delete ALL existing availability records for this room
        $deleted = RoomAvailability::where('room_id', $room->id)->delete();

        // Bulk-insert fresh data
        $toCreate = [];
        foreach ($newSlots as $slot) {
            $toCreate[] = [
                'room_id'        => $room->id,
                'available_date' => $slot['date'],
                'available_time' => $slot['time'],
                'created_at'     => now(),
                'updated_at'     => now(),
            ];
        }

        if (! empty($toCreate)) {
            foreach (array_chunk($toCreate, 100) as $chunk) {
                RoomAvailability::insert($chunk);
            }
        }

        return ['created' => count($toCreate), 'deleted' => $deleted];
    }
}

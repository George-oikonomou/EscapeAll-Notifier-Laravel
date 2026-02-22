<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RoomController extends Controller
{
    /**
     * Display the specified room.
     */
    public function show(Room $room)
    {
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
     * Trigger a GitHub Actions workflow to refresh availability for this room.
     * The workflow will scrape EscapeAll and POST the results back to our webhook.
     * POST /rooms/{room}/refresh-availability
     */
    public function refreshAvailability(Room $room)
    {
        if (empty($room->external_id)) {
            return response()->json(['error' => 'Room has no external ID'], 422);
        }

        $githubToken = config('services.github.token');
        $githubRepo  = config('services.github.repo');

        if (empty($githubToken) || empty($githubRepo)) {
            return response()->json(['error' => 'GitHub integration not configured'], 500);
        }

        try {
            $response = Http::withToken($githubToken)
                ->withHeaders(['Accept' => 'application/vnd.github.v3+json'])
                ->post("https://api.github.com/repos/{$githubRepo}/actions/workflows/refresh-room-availability.yml/dispatches", [
                    'ref'    => 'main',
                    'inputs' => [
                        'room_external_id' => $room->external_id,
                        'room_title'       => $room->title ?? 'Unknown Room',
                    ],
                ]);

            if ($response->status() === 204) {
                return response()->json([
                    'status'  => 'dispatched',
                    'message' => 'Refresh started! Availability will update in ~1-2 minutes.',
                ]);
            }

            return response()->json([
                'error'   => 'Failed to trigger refresh',
                'details' => $response->json(),
            ], $response->status());
        } catch (\Throwable $e) {
            return response()->json([
                'error' => 'Failed to trigger refresh: ' . $e->getMessage(),
            ], 500);
        }
    }
}

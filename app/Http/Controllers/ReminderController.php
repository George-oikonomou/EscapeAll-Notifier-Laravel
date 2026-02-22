<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Reminder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReminderController extends Controller
{
    /**
     * Display the user's reminders.
     */
    public function index()
    {
        $reminders = Auth::user()
            ->reminders()
            ->with(['room.municipality', 'room.company'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('reminders.index', [
            'reminders' => $reminders,
        ]);
    }

    /**
     * Store or update a reminder for a room.
     */
    public function store(Request $request, Room $room)
    {
        $request->validate([
            'type' => 'required|in:' . implode(',', Reminder::TYPES),
            'remind_at' => 'nullable|date|after_or_equal:today',
        ]);

        $user = Auth::user();
        $type = $request->input('type');
        $remindAt = $request->input('remind_at');

        // For coming_soon rooms, force the type
        if ($room->is_coming_soon) {
            $type = Reminder::TYPE_COMING_SOON;
        }

        // For specific_day, require remind_at
        if ($type === Reminder::TYPE_SPECIFIC_DAY && !$remindAt) {
            return response()->json([
                'success' => false,
                'message' => 'Please select a date',
            ], 422);
        }

        $reminder = Reminder::updateOrCreate(
            ['user_id' => $user->id, 'room_id' => $room->id],
            [
                'type' => $type,
                'remind_at' => $type === Reminder::TYPE_SPECIFIC_DAY ? $remindAt : null,
                'notified' => false,
            ]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'has_reminder' => true,
                'type' => $reminder->type,
                'type_label' => $reminder->type_label,
                'message' => 'Reminder set',
            ]);
        }

        return back()->with('success', 'Reminder set');
    }

    /**
     * Remove a reminder for a room.
     */
    public function destroy(Room $room)
    {
        $user = Auth::user();

        Reminder::where('user_id', $user->id)
            ->where('room_id', $room->id)
            ->delete();

        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'has_reminder' => false,
                'message' => 'Reminder removed',
            ]);
        }

        return back()->with('success', 'Reminder removed');
    }

    /**
     * Toggle reminder (simple version for quick actions).
     */
    public function toggle(Request $request, Room $room)
    {
        $user = Auth::user();
        $existing = Reminder::where('user_id', $user->id)
            ->where('room_id', $room->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $hasReminder = false;
            $message = 'Reminder removed';
            $type = null;
            $typeLabel = null;
        } else {
            // Default type based on room status
            $type = $room->is_coming_soon
                ? Reminder::TYPE_COMING_SOON
                : Reminder::TYPE_THIS_MONTH;

            $reminder = Reminder::create([
                'user_id' => $user->id,
                'room_id' => $room->id,
                'type' => $type,
            ]);
            $hasReminder = true;
            $message = 'Reminder set';
            $typeLabel = $reminder->type_label;
        }

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'has_reminder' => $hasReminder,
                'type' => $type,
                'type_label' => $typeLabel,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }
}


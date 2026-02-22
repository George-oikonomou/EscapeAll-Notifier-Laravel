<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Favourite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FavouriteController extends Controller
{
    /**
     * Display the user's favourites.
     */
    public function index()
    {
        $rooms = Auth::user()
            ->favouriteRooms()
            ->with(['municipality', 'company'])
            ->orderBy('favourites.created_at', 'desc')
            ->get();

        return view('favourites.index', [
            'rooms' => $rooms,
        ]);
    }

    /**
     * Toggle favourite status for a room.
     */
    public function toggle(Room $room)
    {
        $user = Auth::user();
        $favourite = Favourite::where('user_id', $user->id)
            ->where('room_id', $room->id)
            ->first();

        if ($favourite) {
            $favourite->delete();
            $isFavourited = false;
            $message = 'Removed from favourites';
        } else {
            Favourite::create([
                'user_id' => $user->id,
                'room_id' => $room->id,
            ]);
            $isFavourited = true;
            $message = 'Added to favourites';
        }

        // Return JSON for AJAX requests
        if (request()->expectsJson()) {
            return response()->json([
                'success' => true,
                'is_favourited' => $isFavourited,
                'message' => $message,
            ]);
        }

        return back()->with('success', $message);
    }

    /**
     * Check if a room is favourited (for API).
     */
    public function check(Room $room)
    {
        $isFavourited = Auth::user()->hasFavourited($room);

        return response()->json([
            'is_favourited' => $isFavourited,
        ]);
    }
}


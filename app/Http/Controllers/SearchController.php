<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Company;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search for rooms and companies.
     * GET /search?q=query
     */
    public function search(Request $request)
    {
        $query = trim($request->input('q', ''));

        if (strlen($query) < 2) {
            return response()->json([
                'rooms' => [],
                'companies' => [],
            ]);
        }

        // Search rooms
        $rooms = Room::where(function ($q) use ($query) {
                $q->where('title', 'LIKE', "%{$query}%")
                  ->orWhere('label', 'LIKE', "%{$query}%")
                  ->orWhere('provider', 'LIKE', "%{$query}%")
                  ->orWhere('short_description', 'LIKE', "%{$query}%");
            })
            ->with('company:id,name')
            ->select('id', 'title', 'provider', 'image_url', 'rating', 'reviews_count', 'min_players', 'max_players', 'company_id')
            ->limit(5)
            ->get()
            ->map(function ($room) {
                return [
                    'id' => $room->id,
                    'title' => $room->title,
                    'provider' => $room->provider,
                    'image_url' => $room->image_url,
                    'rating' => $room->rating,
                    'reviews_count' => $room->reviews_count,
                    'players' => $room->min_players . '-' . $room->max_players,
                    'company_name' => $room->company?->name,
                    'url' => route('rooms.show', $room->id),
                ];
            });

        // Search companies
        $companies = Company::where(function ($q) use ($query) {
                $q->where('name', 'LIKE', "%{$query}%")

                  ->orWhere('address', 'LIKE', "%{$query}%")
                  ->orWhere('full_address', 'LIKE', "%{$query}%");
            })
            ->has('rooms')
            ->withCount('rooms')
            ->limit(5)
            ->get()
            ->map(function ($company) {
                return [
                    'id' => $company->id,
                    'name' => $company->name,
                    'logo_url' => $company->logo_url,
                    'address' => $company->address,
                    'rooms_count' => $company->rooms_count,
                    'url' => route('companies.show', $company->id),
                ];
            });

        return response()->json([
            'rooms' => $rooms,
            'companies' => $companies,
        ]);
    }
}


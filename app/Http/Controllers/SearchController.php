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
        $query = strtolower(trim($request->input('q', '')));

        if (strlen($query) < 2) {
            return response()->json([
                'rooms' => [],
                'companies' => [],
            ]);
        }

        // Search rooms
        $rooms = Room::where(function ($q) use ($query) {
                $q->whereRaw('LOWER(title) LIKE ?', ["%{$query}%"])
                  ->orWhereRaw('LOWER(label) LIKE ?', ["%{$query}%"])
                  ->orWhereRaw('LOWER(provider) LIKE ?', ["%{$query}%"])
                  ->orWhereRaw('LOWER(short_description) LIKE ?', ["%{$query}%"]);
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
                $q->whereRaw('LOWER(name) LIKE ?', ["%{$query}%"])
                  ->orWhereRaw('LOWER(address) LIKE ?', ["%{$query}%"])
                  ->orWhereRaw('LOWER(full_address) LIKE ?', ["%{$query}%"]);
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


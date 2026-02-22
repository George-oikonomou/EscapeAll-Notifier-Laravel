<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Category;
use App\Models\Municipality;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $rooms = Room::query()
            ->with(['municipality:id,name,external_id', 'company:id,name,municipality_external_id'])
            ->orderBy('title')
            ->get();

        // Build municipality lookup by external_id
        $allMunicipalities = Municipality::all()->keyBy('external_id');

        // Get unique municipality external_ids from companies
        $usedMunicipalityExternalIds = $rooms
            ->pluck('company.municipality_external_id')
            ->filter()
            ->unique()
            ->values();

        // Filter to only municipalities that have rooms (through companies)
        $municipalities = $allMunicipalities
            ->filter(fn($m) => $usedMunicipalityExternalIds->contains($m->external_id))
            ->sortBy('name')
            ->pluck('name', 'external_id');

        // Get all categories from database
        $allCategories = Category::all();

        // Build category data with translated names (Greek) for matching
        $greekTranslations = trans('categories', [], 'el');

        // Define negative categories (show rooms that DON'T have a specific tag)
        $negativeCategories = [
            'no-actor' => 'actor',      // "Χωρίς Ηθοποιό" = rooms without "Ηθοποιός"
            'non-horror' => 'horror',   // "Όχι Τρόμου" = rooms without "Τρόμου"
        ];

        // Define aliases for categories (alternative names in scraped data)
        $categoryAliases = [
            'has-score' => ['Σκορ', 'Score'],
            'actor' => ['Με Ηθοποιό'],
            'virtual-reality' => ['Εικονική Πραγματικότητα', 'VR'],
            'sci-fi' => ['Επιστημονική Φαντασία', 'Sci-Fi'],
        ];

        $categories = [];
        $categoryLookup = []; // Greek name -> category data

        foreach ($allCategories as $cat) {
            $greekName = $greekTranslations[$cat->slug] ?? $cat->slug;
            $emoji = $cat->emoji ?? '';

            // If emoji not in DB, try to get from config
            if (empty($emoji)) {
                $configItems = collect(config('categories.items', []));
                $configItem = $configItems->firstWhere('slug', $cat->slug);
                $emoji = $configItem['emoji'] ?? '';
            }

            $isNegative = isset($negativeCategories[$cat->slug]);
            $negatesSlug = $negativeCategories[$cat->slug] ?? null;
            $aliases = $categoryAliases[$cat->slug] ?? [];

            $categoryData = [
                'slug' => $cat->slug,
                'name' => $greekName,
                'emoji' => $emoji,
                'is_negative' => $isNegative,
                'negates_slug' => $negatesSlug,
                'aliases' => $aliases,
            ];

            $categories[] = $categoryData;

            // Add to lookup by Greek name (lowercase)
            $categoryLookup[strtolower($greekName)] = $categoryData;

            // Also add aliases to lookup
            foreach ($aliases as $alias) {
                $categoryLookup[strtolower($alias)] = $categoryData;
            }
        }

        return view('home', [
            'rooms' => $rooms,
            'categories' => $categories,
            'categoryLookup' => $categoryLookup,
            'municipalities' => $municipalities,
            'municipalityLookup' => $allMunicipalities,
        ]);
    }
}


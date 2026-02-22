<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(): View
    {

        $companies = Company::query()
            ->whereHas('rooms')
            ->withCount('rooms')
            ->withAvg('rooms as avg_rating', 'rating')
            ->orderBy('name', 'asc')
            ->get();



        return view('companies.index', [
            'companies' => $companies,
        ]);
    }

    public function show(Company $company): View
    {
        $company->load(['rooms' => function ($q) {
            $q->orderBy('title');
        }]);

        $avgRating = $company->rooms->avg('rating');
        $totalRooms = $company->rooms->count();

        return view('companies.show', [
            'company'    => $company,
            'avgRating'  => $avgRating,
            'totalRooms' => $totalRooms,
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $items = config('categories.items', []);

        foreach ($items as $item) {
            Category::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'code' => $item['code'] ?? null,
                    'icon' => $item['icon'] ?? null,
                    'emoji' => $item['emoji'] ?? null,
                ]
            );
        }

        // Add extra categories that appear in scraped data but not in config
        $extras = [
            ['slug' => 'video', 'code' => 100, 'icon' => 'fa fa-play', 'emoji' => '🎬'],
            ['slug' => 'parking', 'code' => 101, 'icon' => 'fa fa-car', 'emoji' => '🚗'],
        ];

        foreach ($extras as $item) {
            Category::updateOrCreate(
                ['slug' => $item['slug']],
                [
                    'code' => $item['code'],
                    'icon' => $item['icon'],
                    'emoji' => $item['emoji'],
                ]
            );
        }
    }
}

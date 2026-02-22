<?php

namespace Tests\Feature;

use App\Models\Category;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategorySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_all_categories(): void
    {
        $this->seed(CategorySeeder::class);

        $this->assertSame(24, Category::count());

        // Spot check a few entries
        $this->assertDatabaseHas('categories', ['code' => 0, 'name' => 'Με Ηθοποιό']);
        $this->assertDatabaseHas('categories', ['code' => 6, 'name' => 'Kids Friendly']);
        $this->assertDatabaseHas('categories', ['code' => 21, 'name' => 'Φορητό παιχνίδι']);
    }
}


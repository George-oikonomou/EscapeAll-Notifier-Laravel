<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        $title = $this->faker->unique()->words(3, true);
        $provider = $this->faker->company();

        // Ensure we have a municipality to reference
        $municipality = Municipality::query()->inRandomOrder()->first();
        if (!$municipality) {
            $municipality = Municipality::create([
                'external_id' => (string) Str::uuid(),
                'name' => $this->faker->city(),
            ]);
        }

        return [
            'external_id' => (string) Str::uuid(),
            'label' => sprintf('%s - %s, %s', $title, $provider, $municipality->name),
            'title' => $title,
            'provider' => $provider,
            'municipality_id' => $municipality->id,
        ];
    }
}


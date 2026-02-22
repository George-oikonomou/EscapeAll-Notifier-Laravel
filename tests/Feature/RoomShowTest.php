<?php

namespace Tests\Feature;

use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_a_room_page(): void
    {
        $room = Room::factory()->create();

        $res = $this->get(route('rooms.show', $room));
        $res->assertStatus(200);
        $res->assertSee($room->title);
        $res->assertSee($room->provider);
        $res->assertSee($room->external_id);
    }
}


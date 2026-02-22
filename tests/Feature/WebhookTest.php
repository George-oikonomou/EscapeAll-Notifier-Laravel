<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Municipality;
use App\Models\Room;
use App\Models\RoomAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'test-webhook-secret-32chars-long!';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.webhook.secret' => $this->secret]);
    }

    // ──────────────────────────────────────────────
    // Middleware tests
    // ──────────────────────────────────────────────

    public function test_webhook_rejects_missing_secret(): void
    {
        $response = $this->postJson('/api/webhook/sync-companies', [
            'companies' => [],
            'areas'     => [],
        ]);

        $response->assertStatus(403);
    }

    public function test_webhook_rejects_wrong_secret(): void
    {
        $response = $this->postJson('/api/webhook/sync-companies', [
            'companies' => [],
            'areas'     => [],
        ], ['X-Webhook-Secret' => 'wrong-secret']);

        $response->assertStatus(403);
    }

    public function test_webhook_accepts_correct_secret(): void
    {
        $response = $this->postJson('/api/webhook/sync-companies', [
            'companies' => [],
            'areas'     => [],
        ], ['X-Webhook-Secret' => $this->secret]);

        $response->assertStatus(200);
    }

    // ──────────────────────────────────────────────
    // syncCompanies
    // ──────────────────────────────────────────────

    public function test_sync_companies_upserts_areas_and_companies(): void
    {
        $payload = [
            'areas' => [
                ['external_id' => 'area-1', 'name' => 'Athens'],
                ['external_id' => 'area-2', 'name' => 'Piraeus'],
            ],
            'companies' => [
                [
                    'external_id'              => 'company-uuid-1',
                    'name'                     => 'Escape Factory',
                    'logo_url'                 => '/img/logo.png',
                    'latitude'                 => 37.98,
                    'longitude'                => 23.73,
                    'address'                  => '123 Test St',
                    'full_address'             => '123 Test St, Athens',
                    'municipality_external_id' => 'area-1',
                ],
            ],
        ];

        $response = $this->postJson('/api/webhook/sync-companies', $payload, [
            'X-Webhook-Secret' => $this->secret,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'areas'  => ['created' => 2, 'updated' => 0, 'failed' => 0],
                'companies' => ['created' => 1, 'updated' => 0, 'failed' => 0],
            ]);

        $this->assertDatabaseHas('municipalities', ['external_id' => 'area-1', 'name' => 'Athens']);
        $this->assertDatabaseHas('municipalities', ['external_id' => 'area-2', 'name' => 'Piraeus']);
        $this->assertDatabaseHas('companies', ['external_id' => 'company-uuid-1', 'name' => 'Escape Factory']);
    }

    public function test_sync_companies_is_idempotent(): void
    {
        $payload = [
            'areas'     => [['external_id' => 'area-1', 'name' => 'Athens']],
            'companies' => [['external_id' => 'c-1', 'name' => 'Test Co', 'municipality_external_id' => 'area-1']],
        ];

        // First call
        $this->postJson('/api/webhook/sync-companies', $payload, [
            'X-Webhook-Secret' => $this->secret,
        ])->assertStatus(200);

        // Second call — should update, not duplicate
        $response = $this->postJson('/api/webhook/sync-companies', $payload, [
            'X-Webhook-Secret' => $this->secret,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'areas'     => ['created' => 0, 'updated' => 1],
                'companies' => ['created' => 0, 'updated' => 1],
            ]);

        $this->assertDatabaseCount('municipalities', 1);
        $this->assertDatabaseCount('companies', 1);
    }

    // ──────────────────────────────────────────────
    // syncRooms
    // ──────────────────────────────────────────────

    public function test_sync_rooms_upserts_rooms(): void
    {
        // Pre-seed a company
        Company::factory()->create(['external_id' => 'comp-1', 'name' => 'Escape Factory']);

        $payload = [
            'rooms' => [
                [
                    'external_id'         => 'room-uuid-1',
                    'title'               => 'The Haunted Lab',
                    'slug'                => 'the-haunted-lab',
                    'company_name'        => 'Escape Factory',
                    'company_external_id' => 'comp-1',
                    'rating'              => 4.8,
                    'reviews_count'       => 120,
                    'short_description'   => 'A scary room',
                    'duration_minutes'    => 60,
                    'min_players'         => 2,
                    'max_players'         => 6,
                    'escape_rate'         => 35.0,
                    'image_url'           => '/img/room.jpg',
                    'categories'          => ['Horror', 'Thriller'],
                ],
            ],
            'companies' => [],
        ];

        $response = $this->postJson('/api/webhook/sync-rooms', $payload, [
            'X-Webhook-Secret' => $this->secret,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'rooms'  => ['created' => 1, 'updated' => 0, 'failed' => 0],
            ]);

        $this->assertDatabaseHas('rooms', [
            'external_id' => 'room-uuid-1',
            'title'       => 'The Haunted Lab',
            'provider'    => 'Escape Factory',
        ]);
    }

    // ──────────────────────────────────────────────
    // syncAvailability
    // ──────────────────────────────────────────────

    public function test_sync_availability_creates_slots(): void
    {
        $room = Room::factory()->create(['external_id' => 'room-avail-1']);

        $payload = [
            'from'    => '2026-02-22',
            'results' => [
                'room-avail-1' => [
                    [
                        'Day'           => 25,
                        'Month'         => 2,
                        'Year'          => 2026,
                        'HasAvailable'  => true,
                        'AvailabilityTimeSlotList' => [
                            ['Name' => '18:00', 'Quantity' => 1],
                            ['Name' => '20:30', 'Quantity' => 2],
                        ],
                    ],
                    [
                        'Day'           => 26,
                        'Month'         => 2,
                        'Year'          => 2026,
                        'HasAvailable'  => false,
                        'AvailabilityTimeSlotList' => [],
                    ],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhook/sync-availability', $payload, [
            'X-Webhook-Secret' => $this->secret,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'          => 'ok',
                'rooms_with_slots' => 1,
                'total_slots'     => 2,
                'created'         => 2,
            ]);

        $this->assertDatabaseCount('room_availabilities', 2);
        $this->assertDatabaseHas('room_availabilities', [
            'room_id'        => $room->id,
            'available_date' => '2026-02-25',
            'available_time' => '18:00',
        ]);
    }

    // ──────────────────────────────────────────────
    // roomIds
    // ──────────────────────────────────────────────

    public function test_room_ids_returns_all_external_ids(): void
    {
        Room::factory()->create(['external_id' => 'ext-1']);
        Room::factory()->create(['external_id' => 'ext-2']);

        $response = $this->getJson('/api/webhook/room-ids', [
            'X-Webhook-Secret' => $this->secret,
        ]);

        $response->assertStatus(200)
            ->assertJsonCount(2, 'room_ids')
            ->assertJsonFragment(['ext-1'])
            ->assertJsonFragment(['ext-2']);
    }
}

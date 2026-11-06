<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_page_lists_the_rooms(): void
    {
        $office = Office::factory()->create(['name' => 'Москва', 'timezone' => 'Europe/Moscow']);
        Room::factory()->for($office)->create(['name' => 'Арбат', 'capacity' => 6]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Демо: бронирование переговорных')
            ->assertSee('Арбат')
            ->assertSee('Europe/Moscow');
    }

    public function test_demo_page_works_without_any_rooms(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_api_root_describes_the_endpoints(): void
    {
        $this->getJson('/api')
            ->assertOk()
            ->assertJsonPath('endpoints.0.uri', '/api/bookings')
            ->assertJsonCount(4, 'endpoints');
    }
}

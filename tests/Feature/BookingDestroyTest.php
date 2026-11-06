<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingDestroyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixed day used by the tests, so results do not depend on the current clock.
     */
    private const DAY = '2026-11-10';

    public function test_author_can_cancel_the_booking(): void
    {
        $booking = $this->booking();

        Sanctum::actingAs($booking->user);

        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_another_user_cannot_cancel_the_booking(): void
    {
        $booking = $this->booking();

        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/bookings/{$booking->id}")->assertForbidden();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_missing_booking_returns_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson('/api/bookings/999999')->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        $booking = $this->booking();

        $this->deleteJson("/api/bookings/{$booking->id}")->assertUnauthorized();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_cancelled_slot_can_be_booked_again(): void
    {
        $booking = $this->booking();

        Sanctum::actingAs($booking->user);

        $this->deleteJson("/api/bookings/{$booking->id}")->assertNoContent();

        $this->postJson('/api/bookings', [
            'room_id' => $booking->room_id,
            'title' => 'Новая встреча',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();
    }

    /**
     * A booking in the Moscow office on the fixed test day.
     *
     * 07:00 UTC is 10:00 in Moscow.
     */
    private function booking(): Booking
    {
        $room = Room::factory()->create();

        return Booking::factory()->for($room)->create([
            'starts_at' => self::DAY.' 07:00:00',
            'ends_at' => self::DAY.' 08:00:00',
        ]);
    }
}

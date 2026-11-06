<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Office;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingStoreTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fixed day used by the tests, so results do not depend on the current clock.
     */
    private const DAY = '2026-11-10';

    private ?User $actor = null;

    public function test_creates_booking_for_the_authenticated_user(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Планёрка',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])
            ->assertCreated()
            ->assertJsonPath('title', 'Планёрка')
            ->assertJsonPath('room_id', $room->id);

        $booking = Booking::sole();

        $this->assertSame($this->actor()->id, $booking->user_id);
        $this->assertSame('10:00', $booking->starts_at->setTimezone('Europe/Moscow')->format('H:i'));
        $this->assertSame('11:00', $booking->ends_at->setTimezone('Europe/Moscow')->format('H:i'));
    }

    public function test_stores_moments_normalized_to_utc(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Планёрка',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();

        $booking = Booking::sole();

        $this->assertSame('07:00:00', $booking->starts_at->utc()->format('H:i:s'));
        $this->assertSame('08:00:00', $booking->ends_at->utc()->format('H:i:s'));
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/bookings', [])->assertUnauthorized();
    }

    public function test_back_to_back_meeting_after_the_existing_one_is_allowed(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Первая',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Вторая',
            'starts_at' => self::DAY.'T11:00:00+03:00',
            'ends_at' => self::DAY.'T12:00:00+03:00',
        ])->assertCreated();

        $this->assertSame(2, Booking::count());
    }

    public function test_back_to_back_meeting_before_the_existing_one_is_allowed(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Первая',
            'starts_at' => self::DAY.'T11:00:00+03:00',
            'ends_at' => self::DAY.'T12:00:00+03:00',
        ])->assertCreated();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Вторая',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();

        $this->assertSame(2, Booking::count());
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function overlappingIntervals(): array
    {
        return [
            'same interval' => ['10:00', '11:00'],
            'starts before, ends inside' => ['09:45', '10:30'],
            'starts inside, ends after' => ['10:30', '11:30'],
            'fully inside' => ['10:15', '10:45'],
            'fully covers' => ['09:00', '12:00'],
            'ends one minute after the start' => ['10:00', '10:15'],
        ];
    }

    #[DataProvider('overlappingIntervals')]
    public function test_overlapping_booking_is_rejected(string $from, string $to): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Занято',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Конфликт',
            'starts_at' => self::DAY."T{$from}:00+03:00",
            'ends_at' => self::DAY."T{$to}:00+03:00",
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');

        $this->assertSame(1, Booking::count());
    }

    public function test_same_slot_in_another_room_is_allowed(): void
    {
        $first = Room::factory()->create();
        $second = Room::factory()->create();

        $this->postBooking([
            'room_id' => $first->id,
            'title' => 'Первая комната',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();

        $this->postBooking([
            'room_id' => $second->id,
            'title' => 'Вторая комната',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();
    }

    public function test_conflict_is_detected_between_different_offsets(): void
    {
        $room = Room::factory()->create();

        // 10:00+03:00 and 14:00+07:00 are the same instant.
        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Москва',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Новосибирск',
            'starts_at' => self::DAY.'T14:00:00+07:00',
            'ends_at' => self::DAY.'T15:00:00+07:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_conflict_is_detected_when_input_is_in_utc(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Москва',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();

        // 07:00Z is 10:00 MSK.
        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'UTC',
            'starts_at' => self::DAY.'T07:00:00+00:00',
            'ends_at' => self::DAY.'T08:00:00+00:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function workingHours(): array
    {
        return [
            'starts before opening' => ['08:45', '09:45'],
            'ends after closing' => ['19:30', '20:15'],
            'entirely after closing' => ['20:00', '21:00'],
            'entirely before opening' => ['07:00', '08:00'],
        ];
    }

    #[DataProvider('workingHours')]
    public function test_booking_must_fit_into_office_working_hours(string $from, string $to): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Вне рабочих часов',
            'starts_at' => self::DAY."T{$from}:00+03:00",
            'ends_at' => self::DAY."T{$to}:00+03:00",
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_booking_at_exact_working_hour_boundaries_is_allowed(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'С утра',
            'starts_at' => self::DAY.'T09:00:00+03:00',
            'ends_at' => self::DAY.'T10:00:00+03:00',
        ])->assertCreated();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Вечером',
            'starts_at' => self::DAY.'T19:00:00+03:00',
            'ends_at' => self::DAY.'T20:00:00+03:00',
        ])->assertCreated();
    }

    public function test_working_hours_use_the_timezone_of_the_office(): void
    {
        $moscowRoom = Room::factory()->create();
        $novosibirskRoom = Room::factory()->for(Office::factory()->novosibirsk())->create();

        // 10:00 MSK is 14:00 Novosibirsk: working time in both offices.
        $this->postBooking([
            'room_id' => $moscowRoom->id,
            'title' => 'Москва',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();

        $this->postBooking([
            'room_id' => $novosibirskRoom->id,
            'title' => 'Новосибирск',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();

        // 07:00 MSK is 11:00 Novosibirsk: too early for Moscow only.
        $this->postBooking([
            'room_id' => $moscowRoom->id,
            'title' => 'Рано для Москвы',
            'starts_at' => self::DAY.'T07:00:00+03:00',
            'ends_at' => self::DAY.'T08:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');

        $this->postBooking([
            'room_id' => $novosibirskRoom->id,
            'title' => 'Норма для Новосибирска',
            'starts_at' => self::DAY.'T07:00:00+03:00',
            'ends_at' => self::DAY.'T08:00:00+03:00',
        ])->assertCreated();
    }

    public function test_late_moscow_time_is_out_of_hours_in_novosibirsk(): void
    {
        $novosibirskRoom = Room::factory()->for(Office::factory()->novosibirsk())->create();

        // 21:00 MSK is 01:00 of the next day in Novosibirsk.
        $this->postBooking([
            'room_id' => $novosibirskRoom->id,
            'title' => 'Ночь',
            'starts_at' => self::DAY.'T21:00:00+03:00',
            'ends_at' => self::DAY.'T22:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function invalidDurations(): array
    {
        return [
            'shorter than 15 minutes' => ['10:00', '10:05', 'ends_at'],
            'longer than 4 hours' => ['10:00', '14:15', 'ends_at'],
        ];
    }

    #[DataProvider('invalidDurations')]
    public function test_duration_must_be_between_15_minutes_and_4_hours(string $from, string $to, string $field): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Неверная длительность',
            'starts_at' => self::DAY."T{$from}:00+03:00",
            'ends_at' => self::DAY."T{$to}:00+03:00",
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_zero_length_booking_is_rejected(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Ноль',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T10:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_minimum_and_maximum_duration_are_allowed(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Пятнадцать минут',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T10:15:00+03:00',
        ])->assertCreated();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Четыре часа',
            'starts_at' => self::DAY.'T11:00:00+03:00',
            'ends_at' => self::DAY.'T15:00:00+03:00',
        ])->assertCreated();
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function offGridTimes(): array
    {
        return [
            'start is not on the grid' => ['10:07', '11:07', 'starts_at'],
            'end is not on the grid' => ['10:00', '11:05', 'ends_at'],
        ];
    }

    #[DataProvider('offGridTimes')]
    public function test_time_must_be_aligned_to_15_minutes(string $from, string $to, string $field): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Не по сетке',
            'starts_at' => self::DAY."T{$from}:00+03:00",
            'ends_at' => self::DAY."T{$to}:00+03:00",
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    public function test_quarter_hour_start_is_allowed(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'С 10:15',
            'starts_at' => self::DAY.'T10:15:00+03:00',
            'ends_at' => self::DAY.'T10:45:00+03:00',
        ])->assertCreated();
    }

    public function test_dates_are_not_restricted(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Прошлое',
            'starts_at' => '2020-01-15T10:00:00+03:00',
            'ends_at' => '2020-01-15T11:00:00+03:00',
        ])->assertCreated();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Будущее',
            'starts_at' => '2035-06-01T10:00:00+03:00',
            'ends_at' => '2035-06-01T11:00:00+03:00',
        ])->assertCreated();
    }

    public function test_missing_room_is_rejected(): void
    {
        $this->postBooking([
            'title' => 'Без комнаты',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');
    }

    public function test_unknown_room_is_rejected(): void
    {
        $this->postBooking([
            'room_id' => 999999,
            'title' => 'Несуществующая комната',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('room_id');

        $this->assertSame(0, Booking::count());
    }

    public function test_missing_title_is_rejected(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_too_long_title_is_rejected(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => str_repeat('а', 256),
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('title');
    }

    public function test_unparsable_start_is_rejected_with_422(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Мусор',
            'starts_at' => 'завтра',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_null_start_is_rejected_with_422(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Null',
            'starts_at' => null,
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('starts_at');
    }

    public function test_end_before_start_is_rejected(): void
    {
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'title' => 'Наоборот',
            'starts_at' => self::DAY.'T11:00:00+03:00',
            'ends_at' => self::DAY.'T10:00:00+03:00',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_user_id_from_the_payload_is_ignored(): void
    {
        $intruder = User::factory()->create();
        $room = Room::factory()->create();

        $this->postBooking([
            'room_id' => $room->id,
            'user_id' => $intruder->id,
            'title' => 'Подмена автора',
            'starts_at' => self::DAY.'T10:00:00+03:00',
            'ends_at' => self::DAY.'T11:00:00+03:00',
        ])->assertCreated();

        $this->assertSame($this->actor()->id, Booking::sole()->user_id);
    }

    /**
     * The user on whose behalf the requests are made.
     */
    private function actor(): User
    {
        return $this->actor ??= User::factory()->create();
    }

    /**
     * Send a booking request as the current actor of the test.
     *
     * @param  array<string, mixed>  $payload
     */
    private function postBooking(array $payload): TestResponse
    {
        Sanctum::actingAs($this->actor());

        return $this->postJson('/api/bookings', $payload);
    }
}

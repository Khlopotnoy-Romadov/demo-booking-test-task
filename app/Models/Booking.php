<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\BookingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['room_id', 'starts_at', 'ends_at', 'title'])]
class Booking extends Model
{
    /** @use HasFactory<BookingFactory> */
    use HasFactory;

    /**
     * Shortest allowed booking in minutes.
     */
    public const MIN_DURATION_MINUTES = 15;

    /**
     * Longest allowed booking in minutes.
     */
    public const MAX_DURATION_MINUTES = 240;

    /**
     * The grid bookings are aligned to, in minutes.
     */
    public const SLOT_STEP_MINUTES = 15;

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /**
     * Bookings of the room that overlap the given half-open interval.
     *
     * Intervals are treated as [start, end), so back-to-back meetings are not
     * considered a conflict: 10:00–11:00 and 11:00–12:00 do not overlap.
     *
     * @param  Builder<Booking>  $query
     */
    public function scopeOverlapping(Builder $query, int $roomId, CarbonImmutable $startsAt, CarbonImmutable $endsAt): void
    {
        $query->where('room_id', $roomId)
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

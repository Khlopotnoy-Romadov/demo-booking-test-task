<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Determine whether the user can delete the booking.
     */
    public function delete(User $user, Booking $booking): bool
    {
        return $booking->user_id === $user->id;
    }
}

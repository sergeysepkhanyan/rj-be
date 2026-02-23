<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy extends BasePolicy
{
    public function view(User $user, Booking $booking): bool
    {
        return $booking->isOwnedBy($user);
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if (($booking->type ?? 'booking') !== 'booking') {
            return false;
        }

        if (in_array($booking->status, ['cancelled', 'completed'], true)) {
            return false;
        }

        return $booking->isOwnedBy($user);
    }
}

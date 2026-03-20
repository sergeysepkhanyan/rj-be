<?php

namespace App\Support;

use InvalidArgumentException;

class BookingStateMachine
{
    /**
     * Allowed booking status transitions: from => [to, to, ...]
     */
    protected static array $transitions = [
        'pending_payment' => ['confirmed', 'cancelled'],
        'confirmed'       => ['completed', 'cancelled'],
        'completed'       => [],
        'cancelled'       => [],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true; // Idempotent
        }

        $allowed = static::$transitions[$from] ?? [];

        return in_array($to, $allowed, true);
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function assertTransition(string $from, string $to): void
    {
        if (!static::canTransition($from, $to)) {
            throw new InvalidArgumentException(
                "Invalid booking status transition: '{$from}' → '{$to}'"
            );
        }
    }

    public static function allowedTransitions(string $from): array
    {
        return static::$transitions[$from] ?? [];
    }
}

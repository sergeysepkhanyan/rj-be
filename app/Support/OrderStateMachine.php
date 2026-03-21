<?php

namespace App\Support;

use App\Enums\OrderStatus;
use InvalidArgumentException;

class OrderStateMachine
{
    /**
     * Allowed status transitions: from => [to, to, ...]
     */
    protected static array $transitions = [
        'pending'           => ['pending_payment', 'paid', 'cancelled', 'processing', 'gift'],
        'pending_payment'   => ['paid', 'cancelled'],
        'processing'        => ['shipped', 'paid', 'cancelled'],
        'shipped'           => ['fulfilled', 'cancelled'],
        'paid'              => ['refunded', 'fulfilled', 'processing', 'shipped', 'return_requested'],
        'cancelled'         => [],
        'refunded'          => [],
        'fulfilled'         => ['return_requested'],
        'gift'              => [],
        'return_requested'  => ['return_approved', 'return_rejected'],
        'return_approved'   => ['refunded'],
        'return_rejected'   => ['paid', 'fulfilled'],
    ];

    public static function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true; // Idempotent — same status is always allowed
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
                "Invalid order status transition: '{$from}' → '{$to}'"
            );
        }
    }

    public static function allowedTransitions(string $from): array
    {
        return static::$transitions[$from] ?? [];
    }
}

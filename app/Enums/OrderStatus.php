<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case PendingPayment = 'pending_payment';
    case Paid = 'paid';
    case Canceled = 'canceled';
    case Refunded = 'refunded';
    case Fulfilled = 'fulfilled';
}

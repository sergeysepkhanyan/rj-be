<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case PendingPayment = 'pending_payment';
    case Processing = 'processing';
    case Shipped = 'shipped';
    case Paid = 'paid';
    case Canceled = 'cancelled';
    case Refunded = 'refunded';
    case Fulfilled = 'fulfilled';
    case Gift = 'gift';
    case ReturnRequested = 'return_requested';
    case ReturnApproved = 'return_approved';
    case ReturnRejected = 'return_rejected';
}

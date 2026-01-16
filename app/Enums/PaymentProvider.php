<?php

namespace App\Enums;

enum PaymentProvider: string
{
    case Tabby = 'tabby';
    case Stripe = 'stripe';
}


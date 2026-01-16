<?php

namespace App\Enums;

enum OrderType: string
{
    case Booking = 'booking';
    case Ecommerce = 'ecommerce';
}

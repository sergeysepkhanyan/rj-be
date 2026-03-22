<?php

namespace App\Enums;

enum OrderType: string
{
    case Booking = 'booking';
    case Ecommerce = 'ecommerce';
    case GiftCard = 'gift_card';
    case ServicePackage = 'service_package';
}

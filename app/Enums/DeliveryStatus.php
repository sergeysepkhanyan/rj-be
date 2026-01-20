<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Ordered = 'ordered';
    case OutForDelivery = 'out_for_delivery';
    case Arriving = 'arriving';
    case Delivered = 'delivered';
}

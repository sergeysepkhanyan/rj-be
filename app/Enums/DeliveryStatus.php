<?php

namespace App\Enums;

enum DeliveryStatus: string
{
    case Ordered = 'ordered';
    case OutOfDelivery = 'out_of_delivery';
    case Delivered = 'delivered';
    case Canceled = 'canceled';
}

<?php

namespace App\Enums;

enum PaymentFlow: string
{
    case Redirect = 'redirect';
    case TokenCharge = 'token_charge';
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBookingRequest extends StoreBookingRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['paymentMode'], $rules['paymentProvider']);
        return $rules;
    }
}

<?php

namespace App\Http\Requests;

class CancelBookingRequest extends BaseFormRequest
{

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

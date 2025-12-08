<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'isDefault' => 'is_default'
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|string|in:card,apple_pay,google_pay,paypal',
            'brand' => 'required|string',
            'provider' => 'required|string',
            'token' => 'required|string',
            'last4' => 'nullable|string|max:4',
            'isDefault' => 'boolean',
            'meta' => 'nullable|array',
        ];
    }
}

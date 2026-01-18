<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'isDefault' => 'is_default'
    ];

    public function rules(): array
    {
        return [
            'type' => 'required_unless:provider,stripe|string|in:card,apple_pay,google_pay,paypal',
            'brand' => 'required_unless:provider,stripe|string',
            'provider' => 'required|string',
            'token' => 'required|string',
            'last4' => 'nullable|string|max:4',
            'isDefault' => 'boolean',
            'meta' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => __('validation_scoped.payment_method.type.required'),
            'type.string'   => __('validation_scoped.payment_method.type.string'),
            'type.in'       => __('validation_scoped.payment_method.type.in'),

            'brand.required' => __('validation_scoped.payment_method.brand.required'),
            'brand.string'   => __('validation_scoped.payment_method.brand.string'),

            'provider.required' => __('validation_scoped.payment_method.provider.required'),
            'provider.string'   => __('validation_scoped.payment_method.provider.string'),

            'token.required' => __('validation_scoped.payment_method.token.required'),
            'token.string'   => __('validation_scoped.payment_method.token.string'),

            'last4.string' => __('validation_scoped.payment_method.last4.string'),
            'last4.max'    => __('validation_scoped.payment_method.last4.max'),

            'isDefault.boolean' => __('validation_scoped.payment_method.isDefault.boolean'),

            'meta.array' => __('validation_scoped.payment_method.meta.array'),
        ];
    }

}

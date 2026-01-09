<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'isDefault' => 'is_default'
    ];

    public function rules(): array
    {
        return [
            'isDefault' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'isDefault.boolean' => __('validation_scoped.payment_method.isDefault.boolean'),
        ];
    }
}

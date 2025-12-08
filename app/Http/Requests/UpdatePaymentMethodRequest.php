<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodRequest extends BaseFormRequest
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
            'isDefault' => 'boolean',
        ];
    }
}

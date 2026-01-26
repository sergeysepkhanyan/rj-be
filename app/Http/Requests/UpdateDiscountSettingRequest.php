<?php

namespace App\Http\Requests;

class UpdateDiscountSettingRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'quantityThreshold' => 'quantity_threshold',
        'discountPercentage' => 'discount_percentage',
        'discountLabel' => 'discount_label',
    ];

    public function rules(): array
    {
        return [
            'quantityThreshold' => ['required', 'integer', 'min:1'],
            'discountPercentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'discountLabel' => ['required', 'string', 'max:100'],
            'enabled' => ['sometimes', 'boolean'],
        ];
    }
}

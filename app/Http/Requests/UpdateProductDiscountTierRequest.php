<?php

namespace App\Http\Requests;

class UpdateProductDiscountTierRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
        'spendThreshold' => 'spend_threshold',
        'discountPercentage' => 'discount_percentage',
    ];

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'nameAr' => ['nullable', 'string', 'max:255'],
            'spendThreshold' => ['sometimes', 'numeric', 'min:0'],
            'discountPercentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}

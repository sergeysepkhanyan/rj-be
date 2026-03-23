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
            'name_ar' => ['nullable', 'string', 'max:255'],
            'spend_threshold' => ['sometimes', 'numeric', 'min:0'],
            'discount_percentage' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}

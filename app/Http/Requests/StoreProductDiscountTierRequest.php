<?php

namespace App\Http\Requests;

class StoreProductDiscountTierRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
        'spendThreshold' => 'spend_threshold',
        'discountPercentage' => 'discount_percentage',
    ];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'spend_threshold' => ['required', 'numeric', 'min:0'],
            'discount_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

class BulkProductDiscountRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'discountType' => 'discount_type',
        'discountAmount' => 'discount_amount',
    ];

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
            'discount' => ['required', 'boolean'],
            'discountType' => ['required_if:discount,true', 'nullable', 'string', 'in:percentage,amount'],
            'discountAmount' => ['required_if:discount,true', 'nullable', 'numeric', 'min:0.01'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! $this->boolean('discount')) {
                return;
            }
            if ($this->input('discountType') === 'percentage') {
                $amount = (float) $this->input('discountAmount');
                if ($amount > 100) {
                    $v->errors()->add('discountAmount', 'Percentage discount cannot exceed 100.');
                }
            }
        });
    }
}

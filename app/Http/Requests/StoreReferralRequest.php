<?php

namespace App\Http\Requests;

class StoreReferralRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'visitThreshold' => 'visit_threshold',
        'nameAr' => 'name_ar',
    ];

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'name_ar' => ['nullable', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0', 'max:100'],
            'type' => ['nullable', 'string', 'in:percentage,fixed'],
            'visit_threshold' => ['required', 'integer', 'min:0'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}

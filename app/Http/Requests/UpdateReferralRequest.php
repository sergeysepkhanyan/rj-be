<?php

namespace App\Http\Requests;

class UpdateReferralRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'visitThreshold' => 'visit_threshold',
        'nameAr' => 'name_ar',
    ];

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'nameAr' => ['nullable', 'string', 'max:255'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'type' => ['nullable', 'string', 'in:percentage,fixed'],
            'visitThreshold' => ['nullable', 'integer', 'min:0'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}

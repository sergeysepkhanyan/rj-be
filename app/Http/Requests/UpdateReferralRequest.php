<?php

namespace App\Http\Requests;

class UpdateReferralRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'visitThreshold' => 'visit_threshold',
    ];

    public function rules(): array
    {
        return [
            'visit_threshold' => ['nullable', 'integer', 'min:0'],
            'enabled' => ['nullable', 'boolean'],
        ];
    }
}

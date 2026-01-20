<?php

namespace App\Http\Requests;

class CancelOrderRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'reason' => 'reason',
    ];

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}

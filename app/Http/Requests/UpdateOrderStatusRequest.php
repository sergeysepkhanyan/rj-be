<?php

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Validation\Rule;

class UpdateOrderStatusRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'status' => 'status',
        'note' => 'note',
    ];

    public function rules(): array
    {
        return [
            'status' => [
                'required',
                'string',
                Rule::in(array_map(fn($case) => $case->value, OrderStatus::cases())),
            ],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'status.required' => __('validation.required', ['attribute' => __('attributes.status')]),
            'status.in' => __('validation.in', ['attribute' => __('attributes.status')]),
        ];
    }
}

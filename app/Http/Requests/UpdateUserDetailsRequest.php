<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateUserDetailsRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'dateOfBirth' => 'date_of_birth',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'name' => 'sometimes|required|string|max:255',
            'email' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('users', 'email')
                    ->ignore($userId)
                    ->whereNull('deleted_at'),
            ],

            'mobile' => [
                'sometimes',
                'required',
                'string',
                Rule::unique('users', 'mobile')
                    ->ignore($userId)
                    ->whereNull('deleted_at'),
            ],
            'dateOfBirth' => 'sometimes|required|date|date_format:Y-m-d|before_or_equal:' . now()->subYears(18)->toDateString(),
        ];
    }
}


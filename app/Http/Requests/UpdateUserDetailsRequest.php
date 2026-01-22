<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateUserDetailsRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'dateOfBirth' => 'date_of_birth',
    ];

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
                'regex:/^[+\-0-9]+$/',
                Rule::unique('users', 'mobile')
                    ->ignore($userId)
                    ->whereNull('deleted_at'),
            ],
            'dateOfBirth' => 'sometimes|nullable|date|date_format:Y-m-d|before_or_equal:' . now()->subYears(18)->toDateString(),
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('validation.profile.name.required'),
            'name.string'   => __('validation.profile.name.string'),
            'name.max'      => __('validation.profile.name.max'),

            'email.required' => __('validation.profile.email.required'),
            'email.email'    => __('validation.profile.email.email'),
            'email.unique'   => __('validation.profile.email.unique'),

            'mobile.required' => __('validation.profile.mobile.required'),
            'mobile.string'   => __('validation.profile.mobile.string'),
            'mobile.unique'   => __('validation.profile.mobile.unique'),

            'dateOfBirth.date'     => __('validation.profile.dateOfBirth.date'),
            'dateOfBirth.date_format' => __('validation.profile.dateOfBirth.date_format'),
            'dateOfBirth.before_or_equal' => __('validation.profile.dateOfBirth.min_age'),
        ];
    }

}


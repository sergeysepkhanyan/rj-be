<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateUserDetailsRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'dateOfBirth' => 'date_of_birth',
    ];

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
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
            'date_of_birth' => 'sometimes|nullable|date|date_format:Y-m-d|before_or_equal:' . now()->subYears(18)->toDateString(),
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => __('validation.profile.firstName.required'),
            'first_name.string'   => __('validation.profile.firstName.string'),
            'first_name.max'      => __('validation.profile.firstName.max'),

            'last_name.required' => __('validation.profile.lastName.required'),
            'last_name.string'   => __('validation.profile.lastName.string'),
            'last_name.max'      => __('validation.profile.lastName.max'),

            'email.required' => __('validation.profile.email.required'),
            'email.email'    => __('validation.profile.email.email'),
            'email.unique'   => __('validation.profile.email.unique'),

            'mobile.required' => __('validation.profile.mobile.required'),
            'mobile.string'   => __('validation.profile.mobile.string'),
            'mobile.unique'   => __('validation.profile.mobile.unique'),

            'date_of_birth.date'     => __('validation.profile.dateOfBirth.date'),
            'date_of_birth.date_format' => __('validation.profile.dateOfBirth.date_format'),
            'date_of_birth.before_or_equal' => __('validation.profile.dateOfBirth.min_age'),
        ];
    }

}


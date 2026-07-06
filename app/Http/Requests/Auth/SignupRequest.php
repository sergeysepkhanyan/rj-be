<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;
use App\Services\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SignupRequest extends BaseFormRequest
{

    protected array $fieldMap = [
        'passwordConfirmation' => 'password_confirmation',
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'dateOfBirth' => 'date_of_birth',
    ];

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->where(fn ($q) => $q->where('has_account', true)),
            ],
            'mobile' => [
                'required',
                'string',
                'regex:/^[+\-0-9]+$/',
                new \App\Rules\UaePhone,
                // Phone is a match signal, not a unique key — a shared phone (family,
                // front desk) must not block registration. Duplicates surface via the
                // possible-duplicate flag for staff to confirm, never auto-merged.
            ],
            'date_of_birth' => 'nullable|date|date_format:Y-m-d|before_or_equal:' . now()->subYears(18)->format('Y-m-d'),
            'password' => ['required', 'string', 'min:6', 'same:passwordConfirmation'],
            'passwordConfirmation' => ['required', 'string'],
            'redirect_to' => 'nullable|string|max:500',
            'marketing_opt_in' => 'sometimes|boolean',
        ];
    }
}

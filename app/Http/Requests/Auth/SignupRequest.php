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
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'mobile' => [
                'required',
                'string',
                'regex:/^[+\-0-9]+$/',
                Rule::unique('users', 'mobile')->whereNull('deleted_at'),
            ],
            'date_of_birth' => 'nullable|date|date_format:Y-m-d|before_or_equal:' . now()->subYears(18)->format('Y-m-d'),
            'password' => ['required', 'string', 'min:6', 'same:passwordConfirmation'],
            'passwordConfirmation' => ['required', 'string'],
            'redirect_to' => 'nullable|string|max:500',
        ];
    }
}

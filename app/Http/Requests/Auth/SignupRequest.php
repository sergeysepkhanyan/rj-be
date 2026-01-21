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

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'mobile' => [
                'nullable',
                'string',
                'regex:/^[+\-0-9]+$/',
                Rule::unique('users', 'mobile')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:6', 'same:passwordConfirmation'],
            'passwordConfirmation' => ['required', 'string'],
        ];
    }
}

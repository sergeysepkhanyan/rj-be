<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Services\ApiResponse;

class ChangeUserPasswordRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'oldPassword' => 'old_password',
        'passwordConfirmation' => 'password_confirmation',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'oldPassword' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
            'passwordConfirmation' => 'required|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('passwordConfirmation')) {
            $this->merge([
                'password_confirmation' => $this->input('passwordConfirmation')
            ]);
        }
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error($validator->errors(), 'Validation failed', 422)
        );
    }
}


<?php

namespace App\Http\Requests\Auth;

use App\Services\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'nullable|string|max:255',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at')
            ],
            'mobile' => [
                'nullable',
                'string',
                Rule::unique('users', 'mobile')->whereNull('deleted_at'),
            ],
            'password' => 'required|string|min:6|confirmed',
            'passwordConfirmation' => 'required|string',
        ];
    }

    protected function prepareForValidation(): void
    {
        $fieldMap = [
            'passwordConfirmation' => 'password_confirmation',
        ];

        foreach ($fieldMap as $camel => $snake) {
            if ($this->has($camel)) {
                $this->merge([
                    $snake => $this->input($camel),
                ]);
            }
        }
    }


    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error($validator->errors(), 'Validation failed', 422)
        );
    }
}

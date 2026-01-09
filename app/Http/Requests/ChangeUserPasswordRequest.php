<?php

namespace App\Http\Requests;

class ChangeUserPasswordRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'oldPassword' => 'old_password',
        'passwordConfirmation' => 'password_confirmation',
    ];

    public function rules(): array
    {
        return [
            'oldPassword' => 'required|string',
            'password' => 'required|string|min:8|same:passwordConfirmation',
            'passwordConfirmation' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'oldPassword.required' => __('validation_scoped.change_password.oldPassword.required'),
            'oldPassword.string'   => __('validation_scoped.change_password.oldPassword.string'),

            'password.required' => __('validation_scoped.change_password.password.required'),
            'password.string'   => __('validation_scoped.change_password.password.string'),
            'password.min'      => __('validation_scoped.change_password.password.min'),
            'password.same'     => __('validation_scoped.change_password.password.same'),

            'passwordConfirmation.required' => __('validation_scoped.change_password.passwordConfirmation.required'),
            'passwordConfirmation.string'   => __('validation_scoped.change_password.passwordConfirmation.string'),
        ];
    }
}


<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;
use App\Services\ApiResponse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class LoginRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'guestSessionId' => 'guest_session_id',
    ];

    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'guestSessionId' => 'sometimes|string|max:64',
        ];
    }
}

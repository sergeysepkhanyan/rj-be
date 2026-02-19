<?php

namespace App\Http\Requests\Auth;

use App\Http\Requests\BaseFormRequest;

class GoogleAuthRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'guestSessionId' => 'guest_session_id',
    ];

    public function rules(): array
    {
        return [
            'credential' => 'required|string',
            'guestSessionId' => 'sometimes|string|max:64',
        ];
    }
}

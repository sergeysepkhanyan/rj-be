<?php

namespace App\Http\Requests;

class UpdateCartItemRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'guestSessionId' => 'guest_session_id',
    ];

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'integer', 'min:0'],
            'guestSessionId' => ['sometimes', 'string', 'max:64'],
        ];
    }
}

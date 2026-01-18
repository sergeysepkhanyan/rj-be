<?php

namespace App\Http\Requests;

class AddToCartRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'productId' => 'product_id',
        'guestSessionId' => 'guest_session_id',
    ];

    public function rules(): array
    {
        return [
            'productId' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'guestSessionId' => ['sometimes', 'string', 'max:64'],
        ];
    }
}

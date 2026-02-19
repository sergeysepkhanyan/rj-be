<?php

namespace App\Http\Requests;

class StoreInStoreOrderRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'customerName' => 'customer_name',
        'customerEmail' => 'customer_email',
        'customerPhone' => 'customer_phone',
        'paymentMethod' => 'payment_method',
        'sendEmail' => 'send_email',
        'discountType' => 'discount_type',
        'discountValue' => 'discount_value',
        'discountLabel' => 'discount_label',
        'discountAmount' => 'discount_amount',
    ];

    public function rules(): array
    {
        return [
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['nullable', 'email', 'max:255'],
            'customerPhone' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.image' => ['nullable', 'string', 'max:500'],
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'paymentMethod' => ['required', 'string', 'in:cash,card,bank_transfer'],
            'discountType' => ['nullable', 'string', 'in:percentage,fixed,none'],
            'discountValue' => ['nullable', 'numeric', 'min:0'],
            'discountLabel' => ['nullable', 'string', 'max:255'],
            'discountAmount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'sendEmail' => ['sometimes', 'boolean'],
        ];
    }
}

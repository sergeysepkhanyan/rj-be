<?php

namespace App\Http\Requests;

class StoreOrderRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'customerName' => 'customer_name',
        'customerEmail' => 'customer_email',
        'customerPhone' => 'customer_phone',
        'shippingAddress' => 'shipping_address',
        'billingAddress' => 'billing_address',
        'billingSameAsShipping' => 'billing_same_as_shipping',
        'sendEmail' => 'send_email',
    ];

    public function rules(): array
    {
        return [
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:50', 'regex:/^[+\-0-9]+$/'],
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
            'discountType' => ['nullable', 'string', 'in:percentage,fixed,none'],
            'discountValue' => ['nullable', 'numeric', 'min:0'],
            'discountLabel' => ['nullable', 'string', 'max:255'],
            'discountAmount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'shippingAddress' => ['required', 'array'],
            'shippingAddress.name' => ['required', 'string', 'max:255'],
            'shippingAddress.lastName' => ['nullable', 'string', 'max:255'],
            'shippingAddress.mobile' => ['required', 'string', 'regex:/^[+\-0-9]+$/'],
            'shippingAddress.address' => ['required', 'string', 'max:255'],
            'shippingAddress.additionalAddress' => ['nullable', 'string', 'max:100'],
            'shippingAddress.city' => ['required', 'string', 'max:100'],
            'shippingAddress.countryId' => ['required', 'integer', 'exists:countries,id'],
            'shippingAddress.zipCode' => ['nullable', 'string', 'max:20'],
            'billingAddress' => ['nullable', 'array'],
            'billingAddress.name' => ['required_with:billingAddress', 'string', 'max:255'],
            'billingAddress.lastName' => ['nullable', 'string', 'max:255'],
            'billingAddress.mobile' => ['required_with:billingAddress', 'string', 'regex:/^[+\-0-9]+$/'],
            'billingAddress.address' => ['required_with:billingAddress', 'string', 'max:255'],
            'billingAddress.additionalAddress' => ['nullable', 'string', 'max:100'],
            'billingAddress.city' => ['required_with:billingAddress', 'string', 'max:100'],
            'billingAddress.countryId' => ['required_with:billingAddress', 'integer', 'exists:countries,id'],
            'billingAddress.zipCode' => ['nullable', 'string', 'max:20'],
            'billingSameAsShipping' => ['sometimes', 'boolean'],
            'sendEmail' => ['sometimes', 'boolean'],
        ];
    }
}

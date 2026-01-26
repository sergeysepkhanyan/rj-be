<?php

namespace App\Http\Requests;

class CheckoutCartRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'guestSessionId' => 'guest_session_id',
        'customerName' => 'customer_name',
        'customerEmail' => 'customer_email',
        'customerPhone' => 'customer_phone',
        'shippingAddressId' => 'shipping_address_id',
        'billingAddressId' => 'billing_address_id',
        'billingSameAsShipping' => 'billing_same_as_shipping',
        'paymentMethodId' => 'payment_method_id',
        'paymentMethodToken' => 'payment_method_token',
    ];

    public function rules(): array
    {
        return [
            'guestSessionId' => ['sometimes', 'string', 'max:64'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:50', 'regex:/^[+\-0-9]+$/'],
            'shippingAddressId' => ['nullable', 'integer'],
            'billingAddressId' => ['nullable', 'integer'],
            'billingSameAsShipping' => ['sometimes', 'boolean'],
            'paymentMethodId' => ['nullable'],
            'paymentMethodToken' => ['nullable', 'string'],
            'shippingAddress' => ['nullable', 'array'],
            'shippingAddress.name' => ['required_without:shippingAddressId', 'string', 'max:255'],
            'shippingAddress.lastName' => ['nullable', 'string', 'max:255'],
            'shippingAddress.mobile' => ['required_without:shippingAddressId', 'string', 'regex:/^[+\-0-9]+$/'],
            'shippingAddress.address' => ['required_without:shippingAddressId', 'string', 'max:255'],
            'shippingAddress.additionalAddress' => ['nullable', 'string', 'max:100'],
            'shippingAddress.city' => ['required_without:shippingAddressId', 'string', 'max:100'],
            'shippingAddress.state' => ['required_without:shippingAddressId', 'string', 'max:100'],
            'shippingAddress.zipCode' => ['required_without:shippingAddressId', 'string', 'max:20'],
            'billingAddress' => ['nullable', 'array'],
            'billingAddress.name' => ['required_without_all:billingAddressId,billingSameAsShipping', 'string', 'max:255'],
            'billingAddress.lastName' => ['nullable', 'string', 'max:255'],
            'billingAddress.mobile' => ['required_without_all:billingAddressId,billingSameAsShipping', 'string', 'regex:/^[+\-0-9]+$/'],
            'billingAddress.address' => ['required_without_all:billingAddressId,billingSameAsShipping', 'string', 'max:255'],
            'billingAddress.additionalAddress' => ['nullable', 'string', 'max:100'],
            'billingAddress.city' => ['required_without_all:billingAddressId,billingSameAsShipping', 'string', 'max:100'],
            'billingAddress.state' => ['required_without_all:billingAddressId,billingSameAsShipping', 'string', 'max:100'],
            'billingAddress.zipCode' => ['required_without_all:billingAddressId,billingSameAsShipping', 'string', 'max:20'],
        ];
    }
}

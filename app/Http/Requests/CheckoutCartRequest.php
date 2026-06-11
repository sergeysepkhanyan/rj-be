<?php

namespace App\Http\Requests;

class CheckoutCartRequest extends BaseFormRequest
{
    public function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('customer_name') && !$this->has('customerName')) {
            $merge['customerName'] = $this->input('customer_name');
        }
        if ($this->has('customer_email') && !$this->has('customerEmail')) {
            $merge['customerEmail'] = $this->input('customer_email');
        }
        if ($this->has('customer_phone') && !$this->has('customerPhone')) {
            $merge['customerPhone'] = $this->input('customer_phone');
        }
        if ($this->has('shipping_address_id') && !$this->has('shippingAddressId')) {
            $merge['shippingAddressId'] = $this->input('shipping_address_id');
        }
        if ($this->has('billing_address_id') && !$this->has('billingAddressId')) {
            $merge['billingAddressId'] = $this->input('billing_address_id');
        }
        if ($this->has('billing_same_as_shipping') && !$this->has('billingSameAsShipping')) {
            $merge['billingSameAsShipping'] = $this->input('billing_same_as_shipping');
        }
        if ($this->has('payment_method_id') && !$this->has('paymentMethodId')) {
            $merge['paymentMethodId'] = $this->input('payment_method_id');
        }
        if ($this->has('payment_method_token') && !$this->has('paymentMethodToken')) {
            $merge['paymentMethodToken'] = $this->input('payment_method_token');
        }
        if ($this->has('shipping_address') && !$this->has('shippingAddress')) {
            $merge['shippingAddress'] = $this->normalizeAddressForValidation($this->input('shipping_address'));
        }
        if ($this->has('billing_address') && !$this->has('billingAddress')) {
            $merge['billingAddress'] = $this->normalizeAddressForValidation($this->input('billing_address'));
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    private function normalizeAddressForValidation(array $addr): array
    {
        $map = [
            'country_id' => 'countryId',
            'zip_code' => 'zipCode',
            'last_name' => 'lastName',
            'additional_address' => 'additionalAddress',
        ];
        $out = [];
        foreach ($addr as $k => $v) {
            $key = $map[$k] ?? $k;
            $out[$key] = is_array($v) ? $this->normalizeAddressForValidation($v) : $v;
        }
        return $out;
    }

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
        'giftCardCode' => 'gift_card_code',
    ];

    public function rules(): array
    {
        $ownedAddress = \Illuminate\Validation\Rule::exists('addresses', 'id')
            ->where(fn ($q) => $q->where('user_id', $this->user()?->id));

        return [
            'guestSessionId' => ['sometimes', 'string', 'max:64'],
            'customerName' => ['required', 'string', 'max:255'],
            'customerEmail' => ['required', 'email', 'max:255'],
            'customerPhone' => ['required', 'string', 'max:50', 'regex:/^[+\-0-9]+$/'],
            'shippingAddressId' => ['nullable', 'integer', $ownedAddress],
            'billingAddressId' => ['nullable', 'integer', $ownedAddress],
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
            'shippingAddress.countryId' => ['required_without:shippingAddressId', 'integer', 'exists:countries,id'],
            'shippingAddress.zipCode' => ['nullable', 'string', 'max:20'],
            'billingAddress' => ['nullable', 'array'],
            'billingAddress.name' => ['required_without_all:billingAddressId,billingSameAsShipping', 'string', 'max:255'],
            'billingAddress.lastName' => ['nullable', 'string', 'max:255'],
            'billingAddress.mobile' => ['required_without_all:billingAddressId,billingSameAsShipping', 'string', 'regex:/^[+\-0-9]+$/'],
            'billingAddress.address' => ['required_without_all:billingAddressId,billingSameAsShipping', 'string', 'max:255'],
            'billingAddress.additionalAddress' => ['nullable', 'string', 'max:100'],
            'billingAddress.city' => ['required_without_all:billingAddressId,billingSameAsShipping', 'string', 'max:100'],
            'billingAddress.countryId' => ['required_without_all:billingAddressId,billingSameAsShipping', 'integer', 'exists:countries,id'],
            'billingAddress.zipCode' => ['nullable', 'string', 'max:20'],
            'giftCardCode' => ['nullable', 'string', 'max:255'],
        ];
    }
}

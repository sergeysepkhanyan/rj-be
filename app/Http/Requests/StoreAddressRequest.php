<?php

namespace App\Http\Requests;

class StoreAddressRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'isDefault' => 'is_default',
        'lastName' => 'last_name',
        'additionalAddress' => 'additional_address',
        'zipCode' => 'zip_code',
        'countryId' => 'country_id',
        'setDefaultShipping' => 'set_default_shipping',
        'setDefaultBilling'  => 'set_default_billing',
    ];

    public function rules(): array
    {
        return [
            'type' => 'required|in:billing,shipping',
            'isDefault' => 'boolean',
            'name' => 'required|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'mobile' => 'required|string|regex:/^[+\-0-9]+$/',
            'address' => 'required|string|max:255',
            'additionalAddress' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'countryId' => ['required', 'integer', 'exists:countries,id'],
            'zipCode' => 'nullable|string|max:20',
            'setDefaultShipping' => ['sometimes', 'boolean'],
            'setDefaultBilling'  => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('validation_scoped.address.store.name.required'),
            'name.string'   => __('validation_scoped.address.store.name.string'),
            'name.max'      => __('validation_scoped.address.store.name.max'),
        ];
    }
}


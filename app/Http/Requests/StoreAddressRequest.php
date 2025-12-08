<?php

namespace App\Http\Requests;

class StoreAddressRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'isDefault' => 'is_default',
        'lastName' => 'last_name',
        'additionalAddress' => 'additional_address',
        'zipCode' => 'zip_code'
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => 'required|in:billing,shipping',
            'isDefault' => 'boolean',
            'name' => 'required|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'mobile' => 'required|string|max:20',
            'address' => 'required|string|max:255',
            'additionalAddress' => 'nullable|string|max:100',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'zipCode' => 'required|string|max:20',
        ];
    }
}


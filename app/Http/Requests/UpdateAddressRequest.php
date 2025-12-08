<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAddressRequest extends BaseFormRequest
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
            'type' => 'in:billing,shipping',
            'is_default' => 'boolean',
            'name' => 'string|max:255',
            'last_name' => 'nullable|string|max:255',
            'mobile' => 'string|max:20',
            'address' => 'string|max:255',
            'additional_address' => 'nullable|string|max:100',
            'city' => 'string|max:100',
            'state' => 'string|max:100',
            'zip_code' => 'string|max:20',
        ];
    }
}

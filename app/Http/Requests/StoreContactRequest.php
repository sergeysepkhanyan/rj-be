<?php

namespace App\Http\Requests;

use App\Http\Requests\BaseFormRequest;

class StoreContactRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:120'],
            'email'   => ['required', 'email', 'max:190'],
            'phone'   => ['nullable', 'string', 'max:30'],
            'message' => ['required', 'string', 'min:5', 'max:3000'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.max' => 'Bot detected.',
        ];
    }
}


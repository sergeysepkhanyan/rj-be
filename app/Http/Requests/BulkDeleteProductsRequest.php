<?php

namespace App\Http\Requests;

class BulkDeleteProductsRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['nullable', 'array'],
            'ids.*' => ['integer', 'exists:products,id'],
        ];
    }
}

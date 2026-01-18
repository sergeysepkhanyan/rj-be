<?php

namespace App\Http\Requests;

class BulkUpdateProductStatusRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:products,id'],
            'status' => ['required', 'string', 'in:active,draft'],
        ];
    }
}

<?php

namespace App\Http\Requests;

class ImportProductsRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'dryRun' => 'dry_run',
    ];

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            'dryRun' => 'nullable|boolean',
        ];
    }
}

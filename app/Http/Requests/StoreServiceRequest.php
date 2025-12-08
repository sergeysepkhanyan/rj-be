<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreServiceRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'name' => 'name',
        'nameAr' => 'name_ar',
        'description' => 'description',
        'descriptionAr' => 'description_ar',
        'image' => 'image',
    ];
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name')->whereNull('deleted_at'),
            ],

            'nameAr' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name_ar')->whereNull('deleted_at'),
            ],
            'description' => 'required|string',
            'descriptionAr' => 'required|string',
            'image' => 'required|string',
        ];
    }
}

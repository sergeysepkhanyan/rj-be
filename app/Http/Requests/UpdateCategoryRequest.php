<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * @property mixed $gender
 */
class UpdateCategoryRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'name' => 'name',
        'nameAr' => 'name_ar',
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
                'max:255'
            ],

            'nameAr' => [
                'required',
                'string',
                'max:255'
            ],
            'image' => 'nullable|string',
            'gender' => 'required|string|in:Male,Female,Kids',
        ];
    }
}


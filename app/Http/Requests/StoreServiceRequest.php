<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * @property mixed $gender
 */
class StoreServiceRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'name' => 'name',
        'nameAr' => 'name_ar',
        'descriptionAr' => 'description_ar',
        'categoryId' => 'category_id',
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
            'description' => 'required|string',
            'descriptionAr' => 'required|string',
            'image' => 'required|string',
            'categoryId' => 'required|integer|exists:categories,id',
//            'gender' => 'required|string|in:Male,Female,Kids',
        ];
    }
}

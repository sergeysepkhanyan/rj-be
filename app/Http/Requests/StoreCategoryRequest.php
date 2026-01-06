<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * @property mixed $gender
 */
class StoreCategoryRequest extends BaseFormRequest
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
                'max:255',
                Rule::unique('categories', 'name')
                    ->where('gender', $this->gender),
            ],

            'nameAr' => [
                'required',
                'string',
                'max:255',
                Rule::unique('categories', 'name_ar')
                    ->where('gender', $this->gender)
            ],
//            'description' => 'required|string',
//            'descriptionAr' => 'required|string',
            'image' => 'required|string',
            'gender' => 'required|string|in:Male,Female,Kids',
        ];
    }
}

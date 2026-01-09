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
            'image' => 'required|string',
            'gender' => 'required|string|in:Male,Female,Kids',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'  => __('validation_scoped.category.name.required'),
            'name.string'    => __('validation_scoped.category.name.string'),
            'name.max'       => __('validation_scoped.category.name.max'),
            'name.unique'    => __('validation_scoped.category.name.unique'),

            'nameAr.required' => __('validation_scoped.category.nameAr.required'),
            'nameAr.string'   => __('validation_scoped.category.nameAr.string'),
            'nameAr.max'      => __('validation_scoped.category.nameAr.max'),
            'nameAr.unique'   => __('validation_scoped.category.nameAr.unique'),
        ];
    }

}

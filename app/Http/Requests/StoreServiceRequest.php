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
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('validation_scoped.service.name.required'),
            'name.string'   => __('validation_scoped.service.name.string'),
            'name.max'      => __('validation_scoped.service.name.max'),

            'nameAr.required' => __('validation_scoped.service.nameAr.required'),
            'nameAr.string'   => __('validation_scoped.service.nameAr.string'),
            'nameAr.max'      => __('validation_scoped.service.nameAr.max'),

            'description.required' => __('validation_scoped.service.description.required'),
            'description.string'   => __('validation_scoped.service.description.string'),

            'descriptionAr.required' => __('validation_scoped.service.descriptionAr.required'),
            'descriptionAr.string'   => __('validation_scoped.service.descriptionAr.string'),

            'image.required' => __('validation_scoped.service.image.required'),
            'image.string'   => __('validation_scoped.service.image.string'),

            'categoryId.required' => __('validation_scoped.service.categoryId.required'),
            'categoryId.integer'  => __('validation_scoped.service.categoryId.integer'),
            'categoryId.exists'   => __('validation_scoped.service.categoryId.exists'),
        ];
    }

}

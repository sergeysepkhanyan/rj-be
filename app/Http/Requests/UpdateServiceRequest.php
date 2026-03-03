<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * @property mixed $gender
 */
class UpdateServiceRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'name' => 'name',
        'nameAr' => 'name_ar',
        'descriptionAr' => 'description_ar',
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
                'nullable',
                'string',
                'max:255'
            ],
            'description' => 'nullable|string',
            'descriptionAr' => 'nullable|string',
            'image' => 'nullable|string',
            'images' => 'sometimes|array',
            'images.*' => 'sometimes',
            'images.*.id' => 'sometimes|integer',
            'images.*.path' => 'sometimes|string',
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

            'image.string' => __('validation_scoped.service.image.string'),
        ];
    }

}


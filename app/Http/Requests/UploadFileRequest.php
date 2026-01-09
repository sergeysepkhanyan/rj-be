<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadFileRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'slug' => 'required|string',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.required' => __('validation_scoped.upload.slug.required'),
            'slug.string'   => __('validation_scoped.upload.slug.string'),

            'image.required' => __('validation_scoped.upload.image.required'),
            'image.image'    => __('validation_scoped.upload.image.image'),
            'image.mimes'    => __('validation_scoped.upload.image.mimes'),
            'image.max'      => __('validation_scoped.upload.image.max'),
        ];
    }

}


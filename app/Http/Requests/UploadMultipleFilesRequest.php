<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadMultipleFilesRequest extends BaseFormRequest
{

    public function rules(): array
    {
        return [
            'slug' => 'required|string',
            'images' => 'required|array',
            'images.*' => 'required|file|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'slug.required' => __('validation_scoped.upload.slug.required'),
            'slug.string'   => __('validation_scoped.upload.slug.string'),

            'images.required' => __('validation_scoped.upload.images.required'),
            'images.array'    => __('validation_scoped.upload.images.array'),

            'images.*.required' => __('validation_scoped.upload.images_item.required'),
            'images.*.file'     => __('validation_scoped.upload.images_item.file'),
            'images.*.mimes'    => __('validation_scoped.upload.images_item.mimes'),
            'images.*.max'      => __('validation_scoped.upload.images_item.max'),
        ];
    }

}

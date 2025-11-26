<?php

namespace App\Http\Requests;

use App\Services\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CreatePostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'lang'             => ['required', 'string', 'max:5'],
            'title'            => ['required', 'string', 'max:255'],
            'slug'             => ['required', 'string', 'max:255', 'unique:posts,slug'],
            'preview'          => ['required', 'string'],
            'content'          => ['required', 'string'],
            'image'            => ['required', 'string'],
            'show_author'      => ['nullable', 'boolean'],
            'status'           => ['required', 'in:Draft,Published,Archived'],
            'publish_date'     => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * Override failed validation to use custom API response
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error($validator->errors(), 'Validation failed', 422)
        );
    }
}

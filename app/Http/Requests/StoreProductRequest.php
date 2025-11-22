<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Services\ApiResponse;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'description' => 'required|string',
            'description_ar' => 'required|string',
            'max_quantity' => 'nullable|integer',
            'price' => 'required|numeric',
            'currency' => 'required|string|max:10',
            'main_image' => 'nullable|string',
            'referral_id' => 'nullable|integer',
            'discount' => 'nullable|boolean',
            'discount_type' => 'nullable|string|in:percentage,amount',
            'discount_amount' => 'nullable|numeric',
            'status' => 'nullable|in:active,draft',

            'images' => 'required|array|min:1',
            'images.*' => 'string',

            'details' => 'nullable|array',
            'details.*.details' => 'required|string',
            'details.*.details_ar' => 'required|string',
            'details.*.description' => 'nullable|string',
            'details.*.description_ar' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'images.required' => 'At least one file path is required for the product.',
            'images.min' => 'At least one file path is required for the product.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error($validator->errors(), 'Validation failed', 422)
        );
    }
}

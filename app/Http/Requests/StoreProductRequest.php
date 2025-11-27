<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Services\ApiResponse;

class StoreProductRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
        'descriptionAr' => 'description_ar',
        'maxQuantity' => 'max_quantity',
        'mainImage' => 'main_image',
        'referralId' => 'referral_id',
        'discountType' => 'discount_type',
        'discountAmount' => 'discount_amount',
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'nameAr' => 'required|string|max:255',
            'description' => 'required|string',
            'descriptionAr' => 'required|string',
            'maxQuantity' => 'nullable|integer',
            'price' => 'required|numeric',
            'currency' => 'required|string|max:10',
            'mainImage' => 'nullable|string',
            'referralId' => 'nullable|integer',
            'discount' => 'nullable|boolean',
            'discountType' => 'nullable|string|in:percentage,amount',
            'discountAmount' => 'nullable|numeric',
            'status' => 'nullable|in:active,draft',

            'images' => 'required|array|min:1',
            'images.*' => 'string',

            'details' => 'nullable|array',
            'details.*.details' => 'required|string',
            'details.*.detailsAr' => 'required|string',
            'details.*.description' => 'nullable|string',
            'details.*.descriptionAr' => 'nullable|string',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error($validator->errors(), 'Validation failed', 422)
        );
    }
}

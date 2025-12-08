<?php

namespace App\Http\Requests;

class UpdateProductRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
        'descriptionAr' => 'description_ar',
        'mainImage' => 'main_image',
        'maxQuantity' => 'max_quantity',
        'referralId' => 'referral_id',
        'discountType' => 'discount_type',
        'discountAmount' => 'discount_amount',
        'removedFiles' => 'removed_files',
        'newFiles' => 'new_files',
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

            'removedFiles' => 'nullable|array',
            'removedFiles.*' => 'string',

            'newFiles' => 'nullable|array',
            'newFiles.*' => 'string',

            'details' => 'nullable|array',
            'details.*.id' => 'nullable|integer',
            'details.*.details' => 'required|string',
            'details.*.detailsAr' => 'required|string',
            'details.*.description' => 'nullable|string',
            'details.*.descriptionAr' => 'nullable|string',
        ];
    }
}

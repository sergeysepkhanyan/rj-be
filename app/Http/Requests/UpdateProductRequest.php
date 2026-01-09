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

    public function messages(): array
    {
        return [
            'name.required' => __('validation_scoped.product.name.required'),
            'name.string'   => __('validation_scoped.product.name.string'),
            'name.max'      => __('validation_scoped.product.name.max'),

            'nameAr.required' => __('validation_scoped.product.nameAr.required'),
            'nameAr.string'   => __('validation_scoped.product.nameAr.string'),
            'nameAr.max'      => __('validation_scoped.product.nameAr.max'),

            'description.required' => __('validation_scoped.product.description.required'),
            'description.string'   => __('validation_scoped.product.description.string'),

            'descriptionAr.required' => __('validation_scoped.product.descriptionAr.required'),
            'descriptionAr.string'   => __('validation_scoped.product.descriptionAr.string'),

            'price.required' => __('validation_scoped.product.price.required'),
            'price.numeric'  => __('validation_scoped.product.price.numeric'),

            'currency.required' => __('validation_scoped.product.currency.required'),
            'currency.string'   => __('validation_scoped.product.currency.string'),
            'currency.max'      => __('validation_scoped.product.currency.max'),

            'discountType.in' => __('validation_scoped.product.discountType.in'),
            'status.in'       => __('validation_scoped.product.status.in'),

            'removedFiles.array'     => __('validation_scoped.product.removedFiles.array'),
            'removedFiles.*.string'  => __('validation_scoped.product.removedFiles_item.string'),

            'newFiles.array'        => __('validation_scoped.product.newFiles.array'),
            'newFiles.*.string'     => __('validation_scoped.product.newFiles_item.string'),

            'details.array' => __('validation_scoped.product.details.array'),

            'details.*.id.integer' => __('validation_scoped.product.details.id.integer'),

            'details.*.details.required'   => __('validation_scoped.product.details.details.required'),
            'details.*.details.string'     => __('validation_scoped.product.details.details.string'),

            'details.*.detailsAr.required' => __('validation_scoped.product.details.detailsAr.required'),
            'details.*.detailsAr.string'   => __('validation_scoped.product.details.detailsAr.string'),

            'details.*.description.string'   => __('validation_scoped.product.details.description.string'),
            'details.*.descriptionAr.string' => __('validation_scoped.product.details.descriptionAr.string'),
        ];
    }

}

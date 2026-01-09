<?php

namespace App\Http\Requests;

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

            'images.required' => __('validation_scoped.product.images.required'),
            'images.array'    => __('validation_scoped.product.images.array'),
            'images.min'      => __('validation_scoped.product.images.min'),
            'images.*.string' => __('validation_scoped.product.images_item.string'),

            'discountType.in' => __('validation_scoped.product.discountType.in'),
            'status.in'       => __('validation_scoped.product.status.in'),

            'details.*.details.required'   => __('validation_scoped.product.details.details.required'),
            'details.*.detailsAr.required' => __('validation_scoped.product.details.detailsAr.required'),
        ];
    }

}

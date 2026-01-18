<?php

namespace App\Http\Requests;

class UpdateProductRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
        'descriptionAr' => 'description_ar',
        'mainImage' => 'main_image',
        'skuId' => 'sku_id',
        'productCategoryId' => 'product_category_id',
        'productCategory' => 'product_category',
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
            'nameAr' => 'nullable|string|max:255',
            'description' => 'required|string',
            'descriptionAr' => 'nullable|string',
            'skuId' => 'required|string|max:64|unique:products,sku_id,' . $this->route('product'),
            'productCategoryId' => 'nullable|integer|exists:product_categories,id|required_without:productCategory',
            'productCategory' => 'nullable|string|max:255|required_without:productCategoryId',
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

            'skuId.required' => __('validation_scoped.product.skuId.required'),
            'skuId.string'   => __('validation_scoped.product.skuId.string'),
            'skuId.max'      => __('validation_scoped.product.skuId.max'),
            'skuId.unique'   => __('validation_scoped.product.skuId.unique'),

            'productCategoryId.required_without' => __('validation_scoped.product.productCategoryId.required_without'),
            'productCategoryId.integer' => __('validation_scoped.product.productCategoryId.integer'),
            'productCategoryId.exists' => __('validation_scoped.product.productCategoryId.exists'),
            'productCategory.required_without' => __('validation_scoped.product.productCategory.required_without'),
            'productCategory.string' => __('validation_scoped.product.productCategory.string'),
            'productCategory.max' => __('validation_scoped.product.productCategory.max'),

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

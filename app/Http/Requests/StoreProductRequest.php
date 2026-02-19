<?php

namespace App\Http\Requests;

class StoreProductRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
        'descriptionAr' => 'description_ar',
        'skuId' => 'sku_id',
        'productCategoryId' => 'product_category_id',
        'productCategory' => 'product_category',
        'supplierId' => 'supplier_id',
        'maxQuantity' => 'max_quantity',
        'reorderPoint' => 'reorder_point',
        'costPrice' => 'cost_price',
        'productionDate' => 'production_date',
        'expiryDate' => 'expiry_date',
        'unitOfSale' => 'unit_of_sale',
        'salesChannel' => 'sales_channel',
        'productType' => 'product_type',
        'mainImage' => 'main_image',
        'referralId' => 'referral_id',
        'discountType' => 'discount_type',
        'discountAmount' => 'discount_amount',
    ];

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'nameAr' => 'nullable|string|max:255',
            'description' => 'required|string',
            'descriptionAr' => 'nullable|string',
            'skuId' => 'required|string|max:64|unique:products,sku_id',
            'productCategoryId' => 'nullable|integer|exists:product_categories,id|required_without:productCategory',
            'productCategory' => 'nullable|string|max:255|required_without:productCategoryId',
            'supplierId' => 'nullable|integer|exists:suppliers,id',
            'maxQuantity' => 'nullable|integer|min:0',
            'reorderPoint' => 'nullable|integer|min:0',
            'price' => 'required|numeric|min:0',
            'costPrice' => 'nullable|numeric|min:0',
            'currency' => 'required|string|max:10',
            'productionDate' => 'nullable|date',
            'expiryDate' => 'nullable|date|after_or_equal:productionDate',
            'unitOfSale' => 'nullable|string|in:piece,unit,pack,box,bottle,tube,set',
            'salesChannel' => 'nullable|string|in:online,in_store,both',
            'productType' => 'nullable|string|in:retail,professional,both',
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
            'details.*.detailsAr' => 'nullable|string',
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

            'images.required' => __('validation_scoped.product.images.required'),
            'images.array'    => __('validation_scoped.product.images.array'),
            'images.min'      => __('validation_scoped.product.images.min'),
            'images.*.string' => __('validation_scoped.product.images_item.string'),

            'discountType.in' => __('validation_scoped.product.discountType.in'),
            'status.in'       => __('validation_scoped.product.status.in'),

            'details.*.details.required'   => __('validation_scoped.product.details.details.required'),
        ];
    }

}

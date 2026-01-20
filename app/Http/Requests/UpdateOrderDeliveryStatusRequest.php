<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateOrderDeliveryStatusRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'deliveryStatus' => 'delivery_status',
    ];

    public function rules(): array
    {
        return [
            'deliveryStatus' => [
                'required',
                'string',
                Rule::in(['ordered', 'out_for_delivery', 'arriving', 'delivered']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'deliveryStatus.required' => __('validation.required', ['attribute' => __('attributes.delivery_status')]),
            'deliveryStatus.in' => __('validation.in', ['attribute' => __('attributes.delivery_status')]),
        ];
    }
}

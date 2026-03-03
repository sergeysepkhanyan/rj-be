<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreSubServiceRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'serviceId' => 'service_id',
        'nameAr' => 'name_ar',
        'durationUnit' => 'duration_unit',
        'showDuration' => 'show_duration',
        'vatEnabled' => 'vat_enabled',

    ];

    public function rules(): array
    {
        $serviceId = $this->input('serviceId');

        return [
            'serviceId' => [
                'required',
                'integer',
                Rule::exists('services', 'id')->whereNull('deleted_at'),
            ],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_services', 'name')->where(fn($query) =>
                $query->where('service_id', $serviceId)
                ),
            ],
            'nameAr' => [
                'nullable', 'string', 'max:255',
            ],
            'type' => 'required|string|in:Simple,Variant Based',
            'price' => 'required_if:type,Simple|numeric',
            'duration' => 'required_if:type,Simple|numeric',
            'currency' => 'required_if:type,Simple|string',
            'durationUnit' => 'required_if:type,Simple|string',
            'vatEnabled' => ['sometimes', 'boolean'],
            'showDuration' => ['sometimes', 'boolean'],
            'items' => 'required_if:type,Variant Based|array',
            'items.*.name' => 'required_if:type,Variant Based|string|max:255',
            'items.*.nameAr' => 'nullable|string|max:255',
            'items.*.price' => 'required_if:type,Variant Based|numeric',
            'items.*.duration' => 'required_if:type,Variant Based|numeric',
            'items.*.currency' => 'required_if:type,Variant Based|string',
            'items.*.durationUnit' => 'required_if:type,Variant Based|string',
            'items.*.vatEnabled' => ['sometimes', 'boolean'],
            'items.*.showDuration' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'serviceId.required' => __('validation_scoped.subservice.serviceId.required'),
            'serviceId.integer'  => __('validation_scoped.subservice.serviceId.integer'),
            'serviceId.exists'   => __('validation_scoped.subservice.serviceId.exists'),

            'name.required' => __('validation_scoped.subservice.name.required'),
            'name.string'   => __('validation_scoped.subservice.name.string'),
            'name.max'      => __('validation_scoped.subservice.name.max'),
            'name.unique'   => __('validation_scoped.subservice.name.unique'),

            'nameAr.required' => __('validation_scoped.subservice.nameAr.required'),
            'nameAr.string'   => __('validation_scoped.subservice.nameAr.string'),
            'nameAr.max'      => __('validation_scoped.subservice.nameAr.max'),
            'nameAr.unique'   => __('validation_scoped.subservice.nameAr.unique'),

            'type.required' => __('validation_scoped.subservice.type.required'),
            'type.string'   => __('validation_scoped.subservice.type.string'),
            'type.in'       => __('validation_scoped.subservice.type.in'),

            'price.required_if' => __('validation_scoped.subservice.simple.price.required_if'),
            'price.numeric'     => __('validation_scoped.subservice.simple.price.numeric'),

            'duration.required_if' => __('validation_scoped.subservice.simple.duration.required_if'),
            'duration.numeric'     => __('validation_scoped.subservice.simple.duration.numeric'),

            'currency.required_if' => __('validation_scoped.subservice.simple.currency.required_if'),
            'currency.string'      => __('validation_scoped.subservice.simple.currency.string'),

            'durationUnit.required_if' => __('validation_scoped.subservice.simple.durationUnit.required_if'),
            'durationUnit.string'      => __('validation_scoped.subservice.simple.durationUnit.string'),

            'items.required_if' => __('validation_scoped.subservice.items.required_if'),
            'items.array'       => __('validation_scoped.subservice.items.array'),

            'items.*.name.required_if' => __('validation_scoped.subservice.items_fields.name.required_if'),
            'items.*.name.string'      => __('validation_scoped.subservice.items_fields.name.string'),
            'items.*.name.max'         => __('validation_scoped.subservice.items_fields.name.max'),

            'items.*.nameAr.required_if' => __('validation_scoped.subservice.items_fields.nameAr.required_if'),
            'items.*.nameAr.string'      => __('validation_scoped.subservice.items_fields.nameAr.string'),
            'items.*.nameAr.max'         => __('validation_scoped.subservice.items_fields.nameAr.max'),

            'items.*.price.required_if' => __('validation_scoped.subservice.items_fields.price.required_if'),
            'items.*.price.numeric'     => __('validation_scoped.subservice.items_fields.price.numeric'),

            'items.*.duration.required_if' => __('validation_scoped.subservice.items_fields.duration.required_if'),
            'items.*.duration.numeric'     => __('validation_scoped.subservice.items_fields.duration.numeric'),

            'items.*.currency.required_if' => __('validation_scoped.subservice.items_fields.currency.required_if'),
            'items.*.currency.string'      => __('validation_scoped.subservice.items_fields.currency.string'),

            'items.*.durationUnit.required_if' => __('validation_scoped.subservice.items_fields.durationUnit.required_if'),
            'items.*.durationUnit.string'      => __('validation_scoped.subservice.items_fields.durationUnit.string'),

            'vatEnabled.boolean' => __('validation_scoped.subservice.vatEnabled.boolean'),
            'items.*.vatEnabled.boolean' => __('validation_scoped.subservice.items_fields.vatEnabled.boolean'),
        ];
    }

}


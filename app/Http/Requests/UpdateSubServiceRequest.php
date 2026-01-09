<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateSubServiceRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
//        'descriptionAr' => 'description_ar',
        'durationUnit' => 'duration_unit',
        'vatEnabled' => 'vat_enabled',
    ];
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $subServiceId = $this->route('subService')->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_services', 'name')
                    ->ignore($subServiceId)
                    ->where(fn($query) => $query->where('service_id', $this->route('subService')->service_id))
            ],
            'nameAr' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_services', 'name_ar')
                    ->ignore($subServiceId)
                    ->where(fn($query) => $query->where('service_id', $this->route('subService')->service_id))
            ],
//            'description' => 'required|string',
//            'descriptionAr' => 'required|string',
//            'image' => 'nullable|string',
            'price' => 'required_if:type,Simple|numeric',
            'duration' => 'required_if:type,Simple|numeric',
            'currency' => 'required_if:type,Simple|string',
            'durationUnit' => 'required_if:type,Simple|string',
            'vatEnabled' => ['sometimes', 'boolean'],
            'items' => 'required_if:type,Variant Based|array',
            'items.*.id' => 'sometimes|nullable|integer|exists:sub_service_items,id',
            'items.*.name' => 'required:type,Variant Based|string|max:255',
            'items.*.nameAr' => 'required:type,Variant Based|string|max:255',
            'items.*.price' => 'required_if:type,Variant Based|numeric',
            'items.*.duration' => 'required_if:type,Variant Based|numeric',
            'items.*.currency' => 'required_if:type,Variant Based|string',
            'items.*.durationUnit' => 'required_if:type,Variant Based|string',
            'items.*vatEnabled' => ['sometimes', 'boolean'],
        ];
    }
}


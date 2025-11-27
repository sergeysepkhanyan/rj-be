<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Services\ApiResponse;

class UpdateSubServiceRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
        'descriptionAr' => 'description_ar',
        'durationUnit' => 'duration_unit',
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
            'description' => 'required|string',
            'descriptionAr' => 'required|string',
            'image' => 'nullable|string',
            'items' => 'required|array',
            'items.*.id' => 'sometimes|nullable|integer|exists:sub_service_items,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.nameAr' => 'required|string|max:255',
            'items.*.type' => 'required|string|in:Simple,Variant Based',
            'items.*.price' => 'required_if:items.*.type,Simple|nullable|numeric',
            'items.*.duration' => 'required_if:items.*.type,Simple|nullable|numeric',
            'items.*.currency' => 'required_if:items.*.type,Simple|nullable|string',
            'items.*.durationUnit' => 'required_if:items.*.type,Simple|nullable|string',
            'items.*.variants' => 'required_if:items.*.type,Variant Based|array',
            'items.*.variants.*.id' => 'sometimes|nullable|integer|exists:sub_service_item_variants,id',
            'items.*.variants.*.name' => 'required_if:items.*.type,Variant Based|string|max:255',
            'items.*.variants.*.nameAr' => 'required_if:items.*.type,Variant Based|string|max:255',
            'items.*.variants.*.price' => 'required_if:items.*.type,Variant Based|numeric',
            'items.*.variants.*.duration' => 'required_if:items.*.type,Variant Based|numeric',
            'items.*.variants.*.currency' => 'required_if:items.*.type,Variant Based|string',
            'items.*.variants.*.durationUnit' => 'required_if:items.*.type,Variant Based|string',
        ];
    }


    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error($validator->errors(), 'Validation failed', 422)
        );
    }
}


<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Services\ApiResponse;

class StoreSubServiceRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'serviceId' => 'service_id',
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
        $serviceId = $this->input('serviceId');

        return [
            'serviceId' => 'required|integer|exists:services,id',
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_services', 'name')->where(fn($query) =>
                $query->where('service_id', $serviceId)
                ),
            ],
            'nameAr' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_services', 'name_ar')->where(fn($query) =>
                $query->where('service_id', $serviceId)
                ),
            ],
            'description' => 'required|string',
            'descriptionAr' => 'required|string',
            'type' => 'required|string|in:Simple,Variant Based',
            'image' => 'required|string',
            'price' => 'required_if:type,Simple|numeric',
            'duration' => 'required_if:type,Simple|numeric',
            'currency' => 'required_if:type,Simple|string',
            'durationUnit' => 'required_if:type,Simple|string',
            'items' => 'required_if:type,Variant Based|array',
            'items.*.name' => 'required:type,Variant Based|string|max:255',
            'items.*.nameAr' => 'required:type,Variant Based|string|max:255',
            'items.*.price' => 'required_if:type,Variant Based|numeric',
            'items.*.duration' => 'required_if:type,Variant Based|numeric',
            'items.*.currency' => 'required_if:type,Variant Based|string',
            'items.*.durationUnit' => 'required_if:type,Variant Based|string'
        ];
    }


    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error($validator->errors(), 'Validation failed', 422)
        );
    }
}


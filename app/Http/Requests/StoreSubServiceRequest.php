<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use App\Services\ApiResponse;

class StoreSubServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => 'required|integer|exists:services,id',
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_services')->where(fn($query) =>
                $query->where('service_id', $this->input('service_id'))
                ),
            ],
            'name_ar' => [
                'required', 'string', 'max:255',
                Rule::unique('sub_services')->where(fn($query) =>
                $query->where('service_id', $this->input('service_id'))
                ),
            ],
            'description' => 'required|string',
            'description_ar' => 'required|string',
            'image' => 'required|string',
            'items' => 'required|array',
            'items.*.name' => 'required|string|max:255',
            'items.*.name_ar' => 'required|string|max:255',
            'items.*.type' => 'required|string|in:Simple,Variant Based',
            'items.*.price' => 'required_if:items.*.type,Simple|nullable|numeric',
            'items.*.duration' => 'required_if:items.*.type,Simple|nullable|numeric',
            'items.*.currency' => 'required_if:items.*.type,Simple|nullable|string',
            'items.*.duration_unit' => 'required_if:items.*.type,Simple|nullable|string',
            'items.*.variants' => 'required_if:items.*.type,Variant Based|array',
            'items.*.variants.*.name' => 'required_if:items.*.type,Variant Based|string|max:255',
            'items.*.variants.*.name_ar' => 'required_if:items.*.type,Variant Based|string|max:255',
            'items.*.variants.*.price' => 'required_if:items.*.type,Variant Based|numeric',
            'items.*.variants.*.duration' => 'required_if:items.*.type,Variant Based|numeric',
            'items.*.variants.*.currency' => 'required_if:items.*.type,Variant Based|string',
            'items.*.variants.*.duration_unit' => 'required_if:items.*.type,Variant Based|string',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error($validator->errors(), 'Validation failed', 422)
        );
    }
}


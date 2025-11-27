<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use App\Services\ApiResponse;

class UpdateServiceRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'name' => 'name',
        'nameAr' => 'name_ar',
        'description' => 'description',
        'descriptionAr' => 'description_ar',
        'image' => 'image',
    ];
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $serviceId = $this->route('service')->id ?? null;

        return [
            'name' => 'required|string|max:255|unique:services,name,' . $serviceId,
            'nameAr' => 'required|string|max:255|unique:services,name_ar,' . $serviceId,
            'description' => 'required|string',
            'descriptionAr' => 'required|string',
            'image' => 'nullable|string',
        ];
    }


    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(
            ApiResponse::error($validator->errors(), 'Validation failed', 422)
        );
    }
}


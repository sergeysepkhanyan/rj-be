<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

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
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name')
                    ->ignore($serviceId)
                    ->whereNull('deleted_at'),
            ],

            'nameAr' => [
                'required',
                'string',
                'max:255',
                Rule::unique('services', 'name_ar')
                    ->ignore($serviceId)
                    ->whereNull('deleted_at'),
            ],
            'description' => 'required|string',
            'descriptionAr' => 'required|string',
            'image' => 'nullable|string',
            'gender' => 'required|string|in:Male,Female',
        ];
    }
}


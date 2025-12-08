<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreStaffRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
    ];
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'role' => 'required|in:admin,master',
            'name' => 'required|string',
            'nameAr' => 'required_if:role,master|string',
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],

            'mobile' => [
                'required',
                'string',
                Rule::unique('users', 'mobile')->whereNull('deleted_at'),
            ],
            'subservices' => 'nullable|array',
            'subservices.*' => 'exists:sub_services,id',
            'weekends' => 'nullable|array',
            'weekends.*' => 'exists:weekdays,id',
        ];
    }
}


<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateStaffRequest extends BaseFormRequest
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
        $id = $this->route('id');

        return [
            'role' => 'required|in:admin,master',
            'name' => 'required|string',
            'nameAr' => 'required_if:role,master|string',
            'email' => [
                'required_if:role,admin',
                'email',
                Rule::unique('users', 'email')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],

            'mobile' => [
                'required_if:role,admin',
                'string',
                Rule::unique('users', 'mobile')
                    ->ignore($id)
                    ->whereNull('deleted_at'),
            ],
            'subservices' => 'nullable|array',
            'subservices.*' => 'exists:sub_services,id',
            'weekends' => 'nullable|array',
            'weekends.*' => 'exists:weekdays,id',
        ];
    }
}


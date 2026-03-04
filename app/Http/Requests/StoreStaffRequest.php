<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreStaffRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
    ];

    public function rules(): array
    {
        // Email must be globally unique across ALL users (clients, staff, etc.)
        // This prevents conflicts when the same email is used for different account types
        $emailUniqueRule = Rule::unique('users', 'email');
        $mobileUniqueRule = Rule::unique('users', 'mobile');

        return [
            'role' => 'required|in:admin,master,marketer',
            'name' => 'required|string',
            'nameAr' => 'nullable|string',
            'email' => [
                'required',
                'email',
                $emailUniqueRule,
            ],

            'mobile' => [
                'required',
                'string',
                'regex:/^[+\-0-9]+$/',
                $mobileUniqueRule,
            ],
            'subservices' => 'nullable|array',
            'subservices.*' => 'exists:sub_services,id',
            'weekends' => 'nullable|array',
            'weekends.*' => 'exists:weekdays,id',
        ];
    }

    public function messages(): array
    {
        return [
            'role.required' => __('validation_scoped.staff.role.required'),
            'role.in'       => __('validation_scoped.staff.role.in'),

            'name.required' => __('validation_scoped.staff.name.required'),
            'name.string'   => __('validation_scoped.staff.name.string'),

            'nameAr.string' => __('validation_scoped.staff.nameAr.string'),

            'email.required' => __('validation_scoped.staff.email.required'),
            'email.email'    => __('validation_scoped.staff.email.email'),
            'email.unique'   => __('validation_scoped.staff.email.unique'),

            'mobile.required' => __('validation_scoped.staff.mobile.required'),
            'mobile.string'   => __('validation_scoped.staff.mobile.string'),
            'mobile.unique'   => __('validation_scoped.staff.mobile.unique'),

            'subservices.array'   => __('validation_scoped.staff.subservices.array'),
            'subservices.*.exists' => __('validation_scoped.staff.subservices.exists'),

            'weekends.array'   => __('validation_scoped.staff.weekends.array'),
            'weekends.*.exists' => __('validation_scoped.staff.weekends.exists'),
        ];
    }

}


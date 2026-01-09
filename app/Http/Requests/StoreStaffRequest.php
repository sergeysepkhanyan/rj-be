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

    public function messages(): array
    {
        return [
            'role.required' => __('validation_scoped.staff.role.required'),
            'role.in'       => __('validation_scoped.staff.role.in'),

            'name.required' => __('validation_scoped.staff.name.required'),
            'name.string'   => __('validation_scoped.staff.name.string'),

            'nameAr.required_if' => __('validation_scoped.staff.nameAr.required_if'),
            'nameAr.string'      => __('validation_scoped.staff.nameAr.string'),

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


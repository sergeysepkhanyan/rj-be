<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateStaffRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'nameAr' => 'name_ar',
    ];

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'role' => 'required|in:admin,master',
            'name' => 'required|string',
            'nameAr' => 'nullable|string',
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
                'regex:/^[+\-0-9]+$/',
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

    public function messages(): array
    {
        return [
            'role.required' => __('validation_scoped.staff.role.required'),
            'role.in'       => __('validation_scoped.staff.role.in'),

            'name.required' => __('validation_scoped.staff.name.required'),
            'name.string'   => __('validation_scoped.staff.name.string'),

            'nameAr.string' => __('validation_scoped.staff.nameAr.string'),

            'email.required_if' => __('validation_scoped.staff.email.required_if_admin'),
            'email.email'       => __('validation_scoped.staff.email.email'),
            'email.unique'      => __('validation_scoped.staff.email.unique'),

            'mobile.required_if' => __('validation_scoped.staff.mobile.required_if_admin'),
            'mobile.string'      => __('validation_scoped.staff.mobile.string'),
            'mobile.unique'      => __('validation_scoped.staff.mobile.unique'),

            'subservices.array'     => __('validation_scoped.staff.subservices.array'),
            'subservices.*.exists'  => __('validation_scoped.staff.subservices.exists'),

            'weekends.array'        => __('validation_scoped.staff.weekends.array'),
            'weekends.*.exists'     => __('validation_scoped.staff.weekends.exists'),
        ];
    }

}


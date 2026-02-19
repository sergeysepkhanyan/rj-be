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
        $id = $this->route('user')?->id ?? $this->route('id');
        $role = $this->input('role');

        // Get role IDs for the uniqueness check
        $masterRoleId = \App\Models\UserRole::where('slug', 'master')->value('id');
        $adminRoleIds = \App\Models\UserRole::whereIn('slug', ['admin', 'marketer', 'superadmin'])->pluck('id')->toArray();

        // Define which roles share email/mobile uniqueness
        // Masters can use emails/mobiles that admins/marketers have (separate pools)
        if ($role === 'master') {
            // Masters: only check uniqueness among other masters
            $emailUniqueRule = Rule::unique('users', 'email')
                ->ignore($id)
                ->where('user_role_id', $masterRoleId);
            $mobileUniqueRule = Rule::unique('users', 'mobile')
                ->ignore($id)
                ->where('user_role_id', $masterRoleId);
        } else {
            // Admins and marketers must have unique emails/mobiles among themselves
            $emailUniqueRule = Rule::unique('users', 'email')
                ->ignore($id)
                ->whereIn('user_role_id', $adminRoleIds);
            $mobileUniqueRule = Rule::unique('users', 'mobile')
                ->ignore($id)
                ->whereIn('user_role_id', $adminRoleIds);
        }

        return [
            'role' => 'required|in:admin,master,marketer',
            'name' => 'required|string',
            'nameAr' => 'nullable|string',
            'email' => [
                'required_if:role,admin,marketer',
                'email',
                $emailUniqueRule,
            ],

            'mobile' => [
                'required_if:role,admin,marketer',
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


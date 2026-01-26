<?php

namespace App\Http\Requests;

class AddReferralRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'referralId' => 'referral_id',
        'manualReferralId' => 'manual_referral_id',
    ];

    public function rules(): array
    {
        return [
            'referral_id' => 'nullable|exists:referrals,id',
            'manual_referral_id' => 'nullable|exists:referrals,id',
        ];
    }

    public function messages(): array
    {
        return [
            'referral_id.exists' => __('validation.custom.referral_id.exists'),
        ];
    }
}

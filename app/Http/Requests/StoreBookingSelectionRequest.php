<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreBookingSelectionRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'masterId' => 'master_id',
        'serviceId' => 'service_id',
        'serviceType' => 'service_type',
        'startTime' => 'start_time',
        'endTime' => 'end_time',
        'guestSessionId' => 'guest_session_id',
    ];

    public function rules(): array
    {
        return [
            'masterId' => ['nullable', 'integer', 'exists:users,id'],
            'serviceType' => ['required', 'string', Rule::in(['SubService', 'subservice', 'SubServiceItem', 'item'])],
            'serviceId' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'startTime' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'endTime' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'guestSessionId' => ['sometimes', 'string', 'max:64'],
            'anyMaster' => ['sometimes', 'boolean'],
        ];
    }
}

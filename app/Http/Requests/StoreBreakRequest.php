<?php

namespace App\Http\Requests;

class StoreBreakRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'masterId' => 'master_id',
        'startTime' => 'start_time',
        'endTime' => 'end_time',
    ];
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'masterId' => ['required', 'exists:users,id,user_role_id,3'],
            'date' => 'required|date',
            'startTime' => 'required|date_format:H:i',
            'endTime' => 'required|date_format:H:i|after:startTime',
            'notes' => 'nullable|string',
        ];
    }
}


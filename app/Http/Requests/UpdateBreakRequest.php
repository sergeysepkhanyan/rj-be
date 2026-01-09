<?php

namespace App\Http\Requests;

class UpdateBreakRequest extends BaseFormRequest
{
    protected array $fieldMap = [
        'startTime' => 'start_time',
        'endTime'   => 'end_time',
    ];

    public function rules(): array
    {
        return [
            'date'      => ['sometimes', 'required', 'date'],
            'startTime' => ['sometimes', 'required', 'date_format:H:i'],
            'endTime'   => ['sometimes', 'required', 'date_format:H:i'],
            'timezone'  => ['sometimes', 'nullable', 'string'],
            'notes'     => ['sometimes', 'nullable', 'string']
        ];
    }
}


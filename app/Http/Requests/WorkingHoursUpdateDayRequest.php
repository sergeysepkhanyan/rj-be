<?php

namespace App\Http\Requests;


class WorkingHoursUpdateDayRequest extends BaseFormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'isClosed' => ['required', 'boolean'],
            'startTime' => ['nullable', 'date_format:H:i'],
            'endTime' => ['nullable', 'date_format:H:i'],
            'breakStartTime' => ['nullable', 'date_format:H:i'],
            'breakEndTime' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $closed = (bool)$this->input('isClosed', false);
            $start = $this->input('startTime');
            $end   = $this->input('endTime');

            if (!$closed && (!$start || !$end)) {
                $validator->errors()->add('startTime', 'startTime and endTime are required when isClosed is false.');
            }
            if ($start && $end && $start >= $end) {
                $validator->errors()->add('endTime', 'endTime must be after startTime.');
            }

            $bs = $this->input('breakStartTime');
            $be = $this->input('breakEndTime');
            if (($bs && !$be) || (!$bs && $be)) {
                $validator->errors()->add('breakStartTime', 'Both breakStartTime and breakEndTime must be provided together.');
            }
            if ($bs && $be && $bs >= $be) {
                $validator->errors()->add('breakEndTime', 'breakEndTime must be after breakStartTime.');
            }
            if ($bs && $be && $start && $end) {
                if ($bs < $start || $be > $end) {
                    $validator->errors()->add('breakStartTime', 'Break must be within working hours.');
                }
            }
        });
    }
}


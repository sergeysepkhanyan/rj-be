<?php

namespace App\Http\Requests;

class WorkingHoursUpdateDayRequest extends BaseFormRequest
{

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

    public function messages(): array
    {
        return [
            'isClosed.required' => __('validation_scoped.working_hours.isClosed.required'),
            'isClosed.boolean'  => __('validation_scoped.working_hours.isClosed.boolean'),

            'startTime.date_format' => __('validation_scoped.working_hours.startTime.date_format'),
            'endTime.date_format'   => __('validation_scoped.working_hours.endTime.date_format'),

            'breakStartTime.date_format' => __('validation_scoped.working_hours.breakStartTime.date_format'),
            'breakEndTime.date_format'   => __('validation_scoped.working_hours.breakEndTime.date_format'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $closed = (bool)$this->input('isClosed', false);
            $start  = $this->input('startTime');
            $end    = $this->input('endTime');

            if (!$closed && (!$start || !$end)) {
                $validator->errors()->add(
                    'startTime',
                    __('validation_scoped.working_hours.start_end_required_when_open')
                );
            }

            if ($start && $end && $start >= $end) {
                $validator->errors()->add(
                    'endTime',
                    __('validation_scoped.working_hours.end_after_start')
                );
            }

            $bs = $this->input('breakStartTime');
            $be = $this->input('breakEndTime');

            if (($bs && !$be) || (!$bs && $be)) {
                $validator->errors()->add(
                    'breakStartTime',
                    __('validation_scoped.working_hours.break_both_required')
                );
            }

            if ($bs && $be && $bs >= $be) {
                $validator->errors()->add(
                    'breakEndTime',
                    __('validation_scoped.working_hours.break_end_after_start')
                );
            }

            if ($bs && $be && $start && $end) {
                if ($bs < $start || $be > $end) {
                    $validator->errors()->add(
                        'breakStartTime',
                        __('validation_scoped.working_hours.break_within_hours')
                    );
                }
            }
        });
    }
}

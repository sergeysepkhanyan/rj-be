<?php

namespace App\Http\Requests;

class WorkingHoursBulkUpdateRequest extends BaseFormRequest
{
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1'],
            'days.*.day' => ['required', 'integer', 'between:1,7'],
            'days.*.isClosed' => ['required', 'boolean'],

            'days.*.startTime' => ['nullable', 'date_format:H:i'],
            'days.*.endTime' => ['nullable', 'date_format:H:i'],

            'days.*.breakStartTime' => ['nullable', 'date_format:H:i'],
            'days.*.breakEndTime' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function messages(): array
    {
        return [
            'days.required' => __('validation_scoped.working_hours.days.required'),
            'days.array'    => __('validation_scoped.working_hours.days.array'),
            'days.min'      => __('validation_scoped.working_hours.days.min'),

            'days.*.day.required' => __('validation_scoped.working_hours.day.required'),
            'days.*.day.integer'  => __('validation_scoped.working_hours.day.integer'),
            'days.*.day.between'  => __('validation_scoped.working_hours.day.between'),

            'days.*.isClosed.required' => __('validation_scoped.working_hours.isClosed.required'),
            'days.*.isClosed.boolean'  => __('validation_scoped.working_hours.isClosed.boolean'),

            'days.*.startTime.date_format' => __('validation_scoped.working_hours.startTime.date_format'),
            'days.*.endTime.date_format'   => __('validation_scoped.working_hours.endTime.date_format'),

            'days.*.breakStartTime.date_format' => __('validation_scoped.working_hours.breakStartTime.date_format'),
            'days.*.breakEndTime.date_format'   => __('validation_scoped.working_hours.breakEndTime.date_format'),
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $days = $this->input('days', []);

            $nums = array_map(fn ($d) => $d['day'] ?? null, $days);
            if (count($nums) !== count(array_unique($nums))) {
                $validator->errors()->add('days', __('validation_scoped.working_hours.duplicate_days'));
                return;
            }

            foreach ($days as $i => $d) {
                $closed = (bool)($d['isClosed'] ?? false);
                $start  = $d['startTime'] ?? null;
                $end    = $d['endTime'] ?? null;

                if (!$closed && (!$start || !$end)) {
                    $validator->errors()->add(
                        "days.$i.startTime",
                        __('validation_scoped.working_hours.start_end_required_when_open')
                    );
                }

                // 00:00 means midnight (end of day) — always valid as a closing time
                if ($start && $end && $end !== '00:00' && $start >= $end) {
                    $validator->errors()->add(
                        "days.$i.endTime",
                        __('validation_scoped.working_hours.end_after_start')
                    );
                }

                $bs = $d['breakStartTime'] ?? null;
                $be = $d['breakEndTime'] ?? null;

                if (($bs && !$be) || (!$bs && $be)) {
                    $validator->errors()->add(
                        "days.$i.breakStartTime",
                        __('validation_scoped.working_hours.break_both_required')
                    );
                }

                if ($bs && $be && $bs >= $be) {
                    $validator->errors()->add(
                        "days.$i.breakEndTime",
                        __('validation_scoped.working_hours.break_end_after_start')
                    );
                }

                if ($bs && $be && $start && $end) {
                    // When end is 00:00 (midnight), break end only needs to be after start
                    if ($bs < $start || ($end !== '00:00' && $be > $end)) {
                        $validator->errors()->add(
                            "days.$i.breakStartTime",
                            __('validation_scoped.working_hours.break_within_hours')
                        );
                    }
                }
            }
        });
    }
}

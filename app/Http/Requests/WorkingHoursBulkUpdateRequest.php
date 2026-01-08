<?php

namespace App\Http\Requests;

/**
 * @method input(string $string, array $array)
 */
class WorkingHoursBulkUpdateRequest extends BaseFormRequest
{
    public function authorize(): bool { return true; }

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

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $days = $this->input('days', []);

            $nums = array_map(fn ($d) => $d['day'] ?? null, $days);
            if (count($nums) !== count(array_unique($nums))) {
                $validator->errors()->add('days', 'Duplicate day values are not allowed.');
                return;
            }

            foreach ($days as $i => $d) {
                $closed = (bool)($d['isClosed'] ?? false);
                $start = $d['startTime'] ?? null;
                $end   = $d['endTime'] ?? null;

                if (!$closed && (!$start || !$end)) {
                    $validator->errors()->add("days.$i.startTime", 'startTime and endTime are required when isClosed is false.');
                }

                if ($start && $end && $start >= $end) {
                    $validator->errors()->add("days.$i.endTime", 'endTime must be after startTime.');
                }

                $bs = $d['breakStartTime'] ?? null;
                $be = $d['breakEndTime'] ?? null;
                if (($bs && !$be) || (!$bs && $be)) {
                    $validator->errors()->add("days.$i.breakStartTime", 'Both breakStartTime and breakEndTime must be provided together.');
                }
                if ($bs && $be && $bs >= $be) {
                    $validator->errors()->add("days.$i.breakEndTime", 'breakEndTime must be after breakStartTime.');
                }
                if ($bs && $be && $start && $end) {
                    if ($bs < $start || $be > $end) {
                        $validator->errors()->add("days.$i.breakStartTime", 'Break must be within working hours.');
                    }
                }
            }
        });
    }
}

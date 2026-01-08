<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $weekday
 * @property mixed $is_closed
 * @property mixed $start_time
 * @property mixed $end_time
 * @property mixed $break_start_time
 * @property mixed $break_end_time
 */
class WorkingHourResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'day' => $this->weekday->name,
            'isClosed' => $this->is_closed,
            'startTime' => $this->start_time,
            'endTime' => $this->end_time,
            'breakStartTime' => $this->break_start_time,
            'breakEndTime' => $this->break_end_time,
        ];
    }
}


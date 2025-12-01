<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @property mixed $master
 */
class BookingResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? 'Break',
            'description' => $data['notes'] ?? null,
            'type' => $data['type'] ?? 'break',
            'date' => $data['date'] ?? null,
            'startTime' => $data['time'] ?? null,
            'endTime' => $data['end_time'] ?? null,
            'duration' => $data['duration'] ?? null,
            'status' => $data['status'] ?? 'active',
            'master' => $this->when($this->master, new StaffResource($this->master)),
        ];
    }
}



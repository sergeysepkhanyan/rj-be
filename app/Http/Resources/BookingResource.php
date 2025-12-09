<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @property mixed $master
 * @property mixed $date
 * @property mixed $start_time
 * @property mixed $end_time
 * @property mixed $type
 * @property mixed $status
 * @property mixed $total_price
 * @property mixed $final_price
 * @property mixed $notes
 * @property mixed $services
 */
class BookingResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        return [
            'id' => $data['id'] ?? null,
            'date'        => $this->date,
            'start_time'  => $this->start_time,
            'end_time'    => $this->end_time,
            'type'        => $this->type,
            'status'      => $this->status,
            'total_price' => $this->final_price,
            'notes'       => $this->notes,

            'services' => $this->services->map(function ($bs) {
                return [
                    'id'              => $bs->id,
                    'serviceable_type'=> class_basename($bs->serviceable_type),
                    'serviceable_id'  => $bs->serviceable_id,
                    'price'           => $bs->price,
                ];
            }),
            'master' => $this->when($this->master, new StaffResource($this->master)),
        ];
    }
}



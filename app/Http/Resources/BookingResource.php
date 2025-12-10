<?php

namespace App\Http\Resources;

use App\Models\SubService;
use App\Models\SubServiceItem;
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
 * @property mixed $price
 * @property mixed $discount_value
 * @property mixed $discount_type
 */
class BookingResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        return [
            'id' => $data['id'] ?? null,
            'date'        => $this->date,
            'startTime'   => $this->start_time,
            'endTime'     => $this->end_time,
            'type'        => $this->type,
            'status'      => $this->status,
            'price'       => $this->price,
            'discount'    => $this->discount_value . ($this->discount_type === 'percent' ? ' %' : ''),
            'totalPrice'  => $this->final_price,
            'notes'       => $this->notes,

            'services' => $this->services->map(function ($bs) {
                $bookable = $bs->bookable;
                $serviceType = null;
                if ($bookable instanceof SubService) {
                    $serviceType = 'subservice';
                } elseif ($bookable instanceof SubServiceItem) {
                    $serviceType = 'item';
                }

                return [
                    'id'            => $bs->id,
                    'serviceType'  => $serviceType,
                    'serviceId'    => $bookable?->id,
                    'name'          => $bookable?->name,
                    'price'         => $bs->price,
                    'duration'      => $bs->duration_minutes,
                ];
            }),

            'master' => $this->when($this->master, new StaffResource($this->master)),
        ];
    }
}



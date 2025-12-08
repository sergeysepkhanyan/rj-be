<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $type
 * @property mixed $is_default
 * @property mixed $name
 * @property mixed $last_name
 * @property mixed $mobile
 * @property mixed $address
 * @property mixed $additional_address
 * @property mixed $city
 * @property mixed $zip_code
 * @property mixed $state
 */
class AddressResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'isDefault' => (bool) $this->is_default,
            'name' => $this->name,
            'lastName' => $this->last_name,
            'mobile' => $this->mobile,
            'address' => $this->address,
            'additionalAddress' => $this->additional_address ?? null,
            'city' => $this->city,
            'state' => $this->state,
            'zipCode' => $this->zip_code,

//            'order' => $this->whenLoaded('order', function () {
//                return [
//                    'id' => $this->order->id,
//                    'status' => $this->order->status,
//                    'total' => $this->order->total ?? null,
//                    'created_at' => $this->order->created_at?->toDateTimeString(),
//                ];
//            }),
        ];
    }
}




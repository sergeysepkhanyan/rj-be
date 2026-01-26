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
 * @property mixed $country
 * @property mixed $zip_code
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
            'country' => $this->whenLoaded('country', function () {
                return [
                    'id' => $this->country->id,
                    'name' => $this->country->name,
                    'nameAr' => $this->country->name_ar,
                    'code' => $this->country->code,
                ];
            }, function () {
                return $this->country_id ? ['id' => $this->country_id] : null;
            }),
            'zipCode' => $this->zip_code,
        ];
    }
}




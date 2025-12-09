<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $email
 * @property mixed $mobile
 * @property mixed $date_of_birth
 * @property mixed $id
 * @property mixed $subservices
 * @property mixed $weekends
 * @property mixed $masterBookings
 * @property mixed $referral
 * @property mixed $image
 * @method relationLoaded(string $string)
 */
class MasterResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'] ?? null,
        ];
    }
}

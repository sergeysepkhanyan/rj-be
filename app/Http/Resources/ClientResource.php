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
 * @property mixed $clientBookings
 * @method relationLoaded(string $string)
 */
class ClientResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        $roleSlug = $this->role->slug ?? null;
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['description'] ?? null,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'dateOfBirth' => $data['date_of_birth'] ?? null,
            'role' => $this->role->name ?? null,
            'bookingsCount' => $this->clientBookings->count(),
            'ordersCount' => 0,
            'referral' => $this->referral ? new ReferralResource($this->referral) : null
        ];
    }
}

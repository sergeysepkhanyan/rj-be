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
 * @method relationLoaded(string $string)
 */
class StaffResource extends JsonResource
{
    public function toArray($request): array
    {
        $roleSlug =  $this->role->slug ?? null;
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'email' => $this->email ?? null,
            'mobile' => $this->mobile ?? null,
            'date_of_birth' => $this->date_of_birth ?? null,
            'role' => $this->role->name ?? null,
            'bookings_count' => $this->masterBookings->count(),
            'referral' => $this->referral ? new ReferralResource($this->referral) : null,
            'subservices' => $this->when(
                $roleSlug === 'master',
                SubServiceResource::collection($this->whenLoaded('subservices'))
            ),
            'weekends' => $this->when(
                $this->relationLoaded('weekends') || $this->weekends,
                WeekdayResource::collection($this->weekends)
            ),
        ];
    }
}

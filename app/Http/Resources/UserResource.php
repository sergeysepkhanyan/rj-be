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
 * @property mixed $image
 * @property mixed $clientBookings
 * @property mixed $referral
 * @property mixed $masterBookings
 * @method relationLoaded(string $string)
 */
class UserResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        return [
            'id' => $data['id'] ?? null,
            'firstName' => $data['first_name'] ?? null,
            'lastName' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'dateOfBirth' => $data['date_of_birth'] ?? null,
            'role' => $this->role->name ?? null,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'bookingsCount' => $this->client_bookings_count ?? ($this->relationLoaded('clientBookings') ? $this->clientBookings->count() : 0),
            'ordersCount' => 0,
            'referral' => $this->referral ? new ReferralResource($this->referral) : null,
            'tierDiscount' => $this->resource instanceof \App\Models\User ? $this->resource->getActiveTierDiscount() : null,
            'productDiscountTier' => $this->productDiscountTier ? [
                'id' => $this->productDiscountTier->id,
                'name' => $this->productDiscountTier->name,
                'discountPercentage' => $this->productDiscountTier->discount_percentage,
            ] : null,
            'isTemporaryPassword' => (bool) ($data['is_temporary_password'] ?? false),
        ];
    }
}


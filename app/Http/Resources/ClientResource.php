<?php

namespace App\Http\Resources;

use App\Models\BookingReferral;
use App\Models\Order;
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

        // Build full name from first_name + last_name if name is empty
        $fullName = $data['name'] ?? null;
        if (empty($fullName)) {
            $firstName = $data['first_name'] ?? '';
            $lastName = $data['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: null;
        }

        return [
            'id' => $data['id'] ?? null,
            'name' => $fullName,
            'description' => $data['description'] ?? null,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'dateOfBirth' => $data['date_of_birth'] ?? null,
            'role' => $this->role->name ?? null,

            'accountStatus' => ($data['has_account'] ?? false) ? 'registered' : 'guest',
            'customerStatus' => $data['customer_status'] ?? 'lead',
            'contactDeclined' => (bool) ($data['contact_declined'] ?? false),
            'marketingOptIn' => (bool) ($data['marketing_opt_in'] ?? false),
            'registrationSource' => $data['registration_source'] ?? null,
            'bookingsCount' => $this->clientBookings->count(),
            // Mirrors ClientsController::show() so the list count matches the
            // detail page's "Confirmed Orders" stat. Counts ecommerce orders
            // in any post-payment state (paid through refunded).
            'ordersCount' => Order::where('user_id', $data['id'])
                ->where('type', 'ecommerce')
                ->whereIn('status', [
                    'paid',
                    'fulfilled',
                    'processing',
                    'shipped',
                    'return_requested',
                    'return_approved',
                    'return_rejected',
                    'refunded',
                ])
                ->count(),
            'referralCount' => BookingReferral::where('referrer_user_id', $data['id'])->where('status', 'completed')->count(),
            'referral' => $this->referral ? new ReferralResource($this->referral) : null,
            'manualReferral' => $this->whenLoaded('manualReferral', function () {
                return new ReferralResource($this->manualReferral);
            })
        ];
    }
}

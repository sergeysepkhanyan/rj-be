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
 * @property mixed $timezone
 * @property mixed $discount_label
 * @property mixed $cancelledBy
 * @property mixed $cancelled_at
 * @property mixed $cancel_reason
 * @method relationLoaded(string $string)
 */
class BookingResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        $user = $request->user();
        $isAdmin = $user?->isAdmin() ?? false;

        $services = $this->services ?? collect();
        $overallStart = $this->start_time;
        $overallEnd   = $this->end_time;

        if ($services->count() > 0 && $services->first()?->start_time && $services->last()?->end_time) {
            $sorted = $services->sortBy('start_time')->values();
            $overallStart = substr((string) $sorted->first()->start_time, 0, 5);
            $overallEnd   = substr((string) $sorted->last()->end_time, 0, 5);
        }

        return [
            'id'            => $data['id'] ?? null,
            'customerName'  => $data['customer_name'] ?? null,
            'customerEmail' => $data['customer_email'] ?? null,
            'customerPhone' => $data['customer_phone'] ?? null,
            'date'          => $this->date,
            'timezone'      => $this->timezone ?? null,
            'startTime'     => $overallStart,
            'endTime'       => $overallEnd,
            'type'          => $this->type,
            'status'        => $this->status,
            'cancelledBy'   => $this->when($this->cancelledBy, new UserResource($this->cancelledBy)),
            'cancelledAt'   => $this->cancelled_at,
            'cancelReason'  => $this->cancel_reason,
            'price'         => $this->price,
            'discountType'  => $this->discount_type,
            'discountValue' => $this->discount_value,
            'discountLabel' => $this->discount_label,
            'totalPrice'    => $this->final_price,
            'notes'         => $this->notes,
            'services' => $services->map(function ($bs) use ($isAdmin) {
                $bookable = $bs->bookable;

                $serviceType = match (true) {
                    $bookable instanceof SubService => 'subservice',
                    $bookable instanceof SubServiceItem => 'item',
                    default => null,
                };

                $isAny = (bool) ($bs->is_any_master ?? false);
                $canSeeMaster = $isAdmin || !$isAny;

                return [
                    'id'          => $bs->id,
                    'serviceType' => $serviceType,
                    'serviceId'   => $bookable?->id,
                    'name'        => $bookable?->name,
                    'price'       => $bs->price,
                    'duration'    => $bs->duration_minutes,
                    'date'        => $bs->date ?? $this->date,
                    'timezone'    => $bs->timezone ?? $this->timezone,
                    'startTime'   => $bs->start_time ? substr((string) $bs->start_time, 0, 5) : null,
                    'endTime'     => $bs->end_time ? substr((string) $bs->end_time, 0, 5) : null,
                    'isAnyMaster' => $isAny,
                    'master' => $this->when(
                        $canSeeMaster && $bs->relationLoaded('master') && $bs->master,
                        new StaffResource($bs->master)
                    ),
                ];
            })->values(),
            'master' => $this->when(
                $isAdmin && $this->relationLoaded('master') && $this->master,
                new StaffResource($this->master)
            ),
        ];
    }
}



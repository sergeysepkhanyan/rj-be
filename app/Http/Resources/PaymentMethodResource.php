<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $type
 * @property mixed $brand
 * @property mixed $provider
 * @property mixed $token
 * @property mixed $last4
 * @property mixed $is_default
 * @property mixed $meta
 */
class PaymentMethodResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'brand' => $this->brand,
            'provider' => $this->provider,
            'token' => $this->token,
            'last4' => $this->last4,
            'isDefault' => $this->is_default,
            'meta' => $this->meta,
        ];
    }
}



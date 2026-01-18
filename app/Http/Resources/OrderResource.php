<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $type
 * @property mixed $status
 * @property mixed $reference
 * @property mixed $amount
 * @property mixed $currency
 * @property mixed $meta
 * @property mixed $created_at
 * @property mixed $latestPayment
 * @property mixed $deliveryAddress
 * @property mixed $shippingAddress
 * @property mixed $billingAddress
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'type'      => $this->type,
            'status'    => $this->status,
            'reference' => $this->reference,
            'amount'   => (string) $this->amount,
            'currency' => $this->currency,
            'meta' => $this->meta,
            'createdAt' => $this->created_at,
            'latestPayment' => $this->whenLoaded('latestPayment', function () {
                $clientSecret = null;
                if ($this->latestPayment->provider === 'stripe') {
                    $clientSecret = data_get($this->latestPayment->raw, 'client_secret');
                }
                return [
                    'id'            => $this->latestPayment->id,
                    'provider'      => $this->latestPayment->provider,
                    'flow'          => $this->latestPayment->flow,
                    'status'        => $this->latestPayment->status,
                    'externalId'   => $this->latestPayment->external_id,
                    'checkoutUrl'  => $this->latestPayment->checkout_url,
                    'clientSecret' => $clientSecret,
                    'createdAt'    => $this->latestPayment->created_at,
                ];
            }),
            'deliveryAddress' => $this->whenLoaded('deliveryAddress', function () {
                return new AddressResource($this->deliveryAddress);
            }),
            'shippingAddress' => $this->whenLoaded('shippingAddress', function () {
                return new AddressResource($this->shippingAddress);
            }),
            'billingAddress' => $this->whenLoaded('billingAddress', function () {
                return new AddressResource($this->billingAddress);
            }),
        ];
    }
}



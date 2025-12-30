<?php

namespace App\Repositories;

use App\Models\Payment;
use App\Repositories\Interfaces\PaymentRepositoryInterface;

class PaymentRepository implements PaymentRepositoryInterface
{
    public function create(array $data): Payment
    {
        return Payment::create($data);
    }

    public function update(Payment $payment, array $data): Payment
    {
        $payment->update($data);
        return $payment;
    }

    public function findByProviderExternalId(string $provider, string $externalId): ?Payment
    {
        return Payment::where('provider', $provider)
            ->where('external_id', $externalId)
            ->first();
    }
}

<?php

namespace App\Repositories\Interfaces;

use App\Models\Payment;

interface PaymentRepositoryInterface
{
    public function create(array $data): Payment;
    public function update(Payment $payment, array $data): Payment;
    public function findByProviderExternalId(string $provider, string $externalId): ?Payment;
}


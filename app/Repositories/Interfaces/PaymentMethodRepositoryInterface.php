<?php

namespace App\Repositories\Interfaces;

use App\Models\PaymentMethod;
interface PaymentMethodRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod;
    public function delete(PaymentMethod $paymentMethod);
}

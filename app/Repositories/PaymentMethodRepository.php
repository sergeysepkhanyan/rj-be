<?php

namespace App\Repositories;

use App\Models\Address;
use App\Models\PaymentMethod;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class PaymentMethodRepository implements PaymentMethodRepositoryInterface
{
    public function all()
    {
        return PaymentMethod::all();
    }

    public function allForUser(int $userId)
    {
        return PaymentMethod::where('user_id', $userId)
            ->whereNull('order_id')
            ->get();
    }

    public function find($id)
    {
        return PaymentMethod::findOrFail($id);
    }

    public function create(array $data)
    {
        return PaymentMethod::create($data);
    }

    public function update(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        $paymentMethod->update($data);
        return $paymentMethod;
    }

    public function delete(PaymentMethod $paymentMethod): bool
    {
        return $paymentMethod->delete();
    }
}

<?php

namespace App\Services;


use App\Models\PaymentMethod;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;

class PaymentMethodService
{
    protected PaymentMethodRepositoryInterface $paymentMethodRepository;

    public function __construct(
        PaymentMethodRepositoryInterface $paymentMethodRepository,
    )
    {
        $this->paymentMethodRepository = $paymentMethodRepository;
    }

    public function listForUser(int $userId)
    {
        return $this->paymentMethodRepository->allForUser($userId);
    }

    public function getAllPaymentMethods()
    {
        return $this->paymentMethodRepository->all();
    }

    public function createPaymentMethod(array $data)
    {
        if (!empty($data['is_default'])) {
            PaymentMethod::where('user_id', $data['user_id'])
                ->update(['is_default' => false]);
        }
        return $this->paymentMethodRepository->create($data);
    }

    public function updatePaymentMethod(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        if (!empty($data['is_default'])) {
            PaymentMethod::where('user_id', $paymentMethod->user_id)
                ->update(['is_default' => false]);
        }
        return $this->paymentMethodRepository->update($paymentMethod, $data);
    }

    public function delete(PaymentMethod $paymentMethod): bool
    {
        return $this->paymentMethodRepository->delete($paymentMethod);
    }
}


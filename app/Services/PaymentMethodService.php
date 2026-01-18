<?php

namespace App\Services;


use App\Integrations\Stripe\StripeClient;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;

class PaymentMethodService
{
    public function __construct(
       protected PaymentMethodRepositoryInterface $paymentMethodRepository,
       protected StripeClient $stripeClient,
    ){}

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

        if (($data['provider'] ?? null) === 'stripe') {
            $data = $this->syncStripePaymentMethod($data);
        }

        return $this->paymentMethodRepository->create($data);
    }

    public function updatePaymentMethod(PaymentMethod $paymentMethod, array $data): PaymentMethod
    {
        if (!empty($data['is_default'])) {
            PaymentMethod::where('user_id', $paymentMethod->user_id)
                ->update(['is_default' => false]);
        }

        if (!empty($data['is_default']) && $paymentMethod->provider === 'stripe') {
            $user = User::find($paymentMethod->user_id);
            if ($user?->stripe_customer_id) {
                $this->stripeClient->updateCustomerDefaultPaymentMethod(
                    $user->stripe_customer_id,
                    $paymentMethod->token
                );
            }
        }

        return $this->paymentMethodRepository->update($paymentMethod, $data);
    }

    public function delete(PaymentMethod $paymentMethod): bool
    {
        return $this->paymentMethodRepository->delete($paymentMethod);
    }

    protected function syncStripePaymentMethod(array $data): array
    {
        $user = User::find($data['user_id']);
        if (!$user) {
            return $data;
        }

        if (!$user->stripe_customer_id) {
            $customer = $this->stripeClient->createCustomer([
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->mobile,
                'metadata[user_id]' => (string) $user->id,
            ]);
            $user->update(['stripe_customer_id' => $customer['id'] ?? null]);
        }

        $customerId = $user->stripe_customer_id;
        $paymentMethodId = $data['token'] ?? null;
        if ($customerId && $paymentMethodId) {
            $this->stripeClient->attachPaymentMethod($paymentMethodId, $customerId);
            $pm = $this->stripeClient->retrievePaymentMethod($paymentMethodId);

            $data['type'] = $pm['type'] ?? ($data['type'] ?? 'card');
            $data['brand'] = $pm['card']['brand'] ?? ($data['brand'] ?? null);
            $data['last4'] = $pm['card']['last4'] ?? ($data['last4'] ?? null);
            $data['meta'] = array_merge($data['meta'] ?? [], [
                'stripe_payment_method_id' => $paymentMethodId,
                'stripe_customer_id' => $customerId,
            ]);

            if (!empty($data['is_default'])) {
                $this->stripeClient->updateCustomerDefaultPaymentMethod($customerId, $paymentMethodId);
            }
        }

        return $data;
    }
}


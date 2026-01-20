<?php

namespace App\Services;


use App\Integrations\Stripe\StripeClient;
use App\Models\PaymentMethod;
use App\Models\User;
use App\Repositories\Interfaces\PaymentMethodRepositoryInterface;
use Illuminate\Support\Facades\Log;

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
        // Delete from Stripe if provider is stripe
        if ($paymentMethod->provider === 'stripe' && $paymentMethod->token) {
            try {
                $this->stripeClient->detachPaymentMethod($paymentMethod->token);
            } catch (\Exception $e) {
                // Log error but continue with local deletion
                // Payment method might already be deleted in Stripe
                \Log::warning("Failed to detach Stripe payment method: {$e->getMessage()}", [
                    'payment_method_id' => $paymentMethod->id,
                    'stripe_payment_method_id' => $paymentMethod->token,
                ]);
            }
        }

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

    /**
     * After a successful Stripe payment, store the payment method locally for the user (if not already stored).
     * This is used by the Stripe webhook flow.
     */
    public function ensureStripePaymentMethodSaved(int $userId, ?string $stripePaymentMethodId): ?PaymentMethod
    {
        $stripePaymentMethodId = $stripePaymentMethodId ? trim($stripePaymentMethodId) : null;
        if (!$stripePaymentMethodId) {
            return null;
        }

        $existing = PaymentMethod::query()
            ->where('user_id', $userId)
            ->where('provider', 'stripe')
            ->where('token', $stripePaymentMethodId)
            ->first();

        if ($existing) {
            return $existing;
        }

        $user = User::find($userId);
        if (!$user) {
            return null;
        }

        // Ensure Stripe customer exists so the method can be reused later.
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

        // Attach to customer (safe to try; Stripe may respond with "already attached" in some cases).
        if ($customerId) {
            try {
                $this->stripeClient->attachPaymentMethod($stripePaymentMethodId, $customerId);
            } catch (\Throwable $e) {
                Log::warning('Stripe attachPaymentMethod failed (continuing)', [
                    'user_id' => $userId,
                    'stripe_customer_id' => $customerId,
                    'stripe_payment_method_id' => $stripePaymentMethodId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $pm = $this->stripeClient->retrievePaymentMethod($stripePaymentMethodId);

        $isDefault = !PaymentMethod::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->exists();

        if ($isDefault) {
            PaymentMethod::where('user_id', $userId)->update(['is_default' => false]);
        }

        $created = $this->paymentMethodRepository->create([
            'user_id' => $userId,
            'provider' => 'stripe',
            'token' => $stripePaymentMethodId,
            'type' => data_get($pm, 'type', 'card'),
            'brand' => data_get($pm, 'card.brand', ''),
            'last4' => data_get($pm, 'card.last4'),
            'is_default' => $isDefault,
            'meta' => [
                'stripe_payment_method_id' => $stripePaymentMethodId,
                'stripe_customer_id' => $customerId,
                'exp_month' => data_get($pm, 'card.exp_month'),
                'exp_year' => data_get($pm, 'card.exp_year'),
            ],
        ]);

        if ($isDefault && $customerId) {
            try {
                $this->stripeClient->updateCustomerDefaultPaymentMethod($customerId, $stripePaymentMethodId);
            } catch (\Throwable $e) {
                Log::warning('Stripe updateCustomerDefaultPaymentMethod failed (continuing)', [
                    'user_id' => $userId,
                    'stripe_customer_id' => $customerId,
                    'stripe_payment_method_id' => $stripePaymentMethodId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }
}


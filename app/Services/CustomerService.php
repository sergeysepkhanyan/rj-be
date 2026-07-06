<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Order;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Single entry point for turning transaction contact details into a unified
 * customer record (users row, role "client"). Email is the unique identity key;
 * phone is only a match signal for staff-confirmed dedup, never an auto-merge key.
 */
class CustomerService
{
    public function clientRoleId(): int
    {
        return (int) UserRole::query()->where('slug', 'client')->value('id');
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = is_string($email) ? trim(strtolower($email)) : null;

        return $email !== '' ? $email : null;
    }

    /**
     * Resolve (or create) the customer behind a transaction's contact details.
     * Always returns a record so a sale is never dropped for missing contact.
     *
     * @param array{name?:?string,first_name?:?string,last_name?:?string,email?:?string,phone?:?string,source?:?string,declined?:bool} $contact
     */
    public function resolveForTransaction(array $contact): User
    {
        $email = $this->normalizeEmail($contact['email'] ?? null);
        $phone = $this->cleanString($contact['phone'] ?? null);
        $name = $this->resolveName($contact);
        $source = $contact['source'] ?? null;
        $declined = (bool) ($contact['declined'] ?? false);

        if ($email) {
            $existing = User::where('email', $email)->first();
            if ($existing) {
                $this->fillMissingContact($existing, $name, $phone, $contact);
                $this->applyMarketingConsent($existing, $contact);

                return $existing;
            }
        }

        return $this->createCustomer($email, $phone, $name, $contact, $source, $declined);
    }

    /**
     * Promote a customer to "client" on a captured payment. Forward-only:
     * a later refund or cancellation never reverts the status.
     */
    public function markTransacted(?User $customer, ?\DateTimeInterface $when = null): void
    {
        if (! $customer) {
            return;
        }

        $changes = [];
        if ($customer->customer_status !== 'client') {
            $changes['customer_status'] = 'client';
        }
        if (! $customer->first_transacted_at) {
            $changes['first_transacted_at'] = $when ? Carbon::instance(Carbon::parse($when)) : now();
        }

        if ($changes) {
            $customer->forceFill($changes)->save();
        }
    }

    /**
     * Register or upgrade an account keyed by email. If a guest customer already
     * exists for the email, it is upgraded in place (preserving its history and
     * loyalty) rather than duplicated. Returns the account user.
     *
     * @param array<string,mixed> $attributes
     */
    public function registerOrUpgrade(string $email, array $attributes): User
    {
        $email = $this->normalizeEmail($email);
        $existing = $email ? User::where('email', $email)->first() : null;

        if ($existing) {
            $existing->forceFill(array_merge($attributes, [
                'email' => $email,
                'has_account' => true,
            ]));
            if (! $existing->user_role_id) {
                $existing->user_role_id = $this->clientRoleId();
            }
            if (! $existing->unsubscribe_token) {
                $existing->unsubscribe_token = Str::random(48);
            }
            $existing->save();
            $this->linkGuestTransactions($existing);

            return $existing;
        }

        $user = new User();
        $user->forceFill(array_merge([
            'user_role_id' => $this->clientRoleId(),
            'customer_status' => 'lead',
            'unsubscribe_token' => Str::random(48),
        ], $attributes, [
            'email' => $email,
            'has_account' => true,
        ]));
        $user->save();
        $this->linkGuestTransactions($user);

        return $user;
    }

    /**
     * Attach prior guest transactions (placed with no account but the same email)
     * to a now-registered account so their history and spend follow them.
     */
    public function linkGuestTransactions(User $customer): void
    {
        if (! $customer->email) {
            return;
        }

        Order::whereNull('user_id')
            ->whereNotNull('meta')
            ->whereRaw('JSON_VALID(meta)')
            ->whereRaw('LOWER(JSON_UNQUOTE(JSON_EXTRACT(meta, "$.customer_email"))) = ?', [$customer->email])
            ->update(['user_id' => $customer->id]);

        Booking::whereNull('user_id')
            ->whereRaw('LOWER(customer_email) = ?', [$customer->email])
            ->update(['user_id' => $customer->id]);
    }

    /**
     * Customers that share this customer's phone but have a different email —
     * surfaced for staff to confirm a merge. Never auto-merged.
     */
    public function possibleDuplicates(User $customer): Collection
    {
        if (! $customer->mobile) {
            return collect();
        }

        return User::customers()
            ->where('id', '!=', $customer->id)
            ->where('mobile', $customer->mobile)
            ->where(function ($q) use ($customer) {
                $q->whereNull('email')->orWhere('email', '!=', $customer->email);
            })
            ->get();
    }

    /**
     * Staff-confirmed merge of a phone-matched duplicate INTO a primary client.
     * Reassigns transactional + loyalty history, promotes the primary forward-only,
     * fills any missing contact, then soft-deletes the duplicate. Never merges on
     * phone automatically — this is only reached after a staff confirm action.
     */
    public function mergeCustomers(User $primary, User $duplicate): User
    {
        if ($primary->id === $duplicate->id) {
            return $primary;
        }

        return DB::transaction(function () use ($primary, $duplicate) {
            Order::where('user_id', $duplicate->id)->update(['user_id' => $primary->id]);
            Booking::where('user_id', $duplicate->id)->update(['user_id' => $primary->id]);
            \App\Models\ServicePackagePurchase::where('user_id', $duplicate->id)->update(['user_id' => $primary->id]);
            \App\Models\ComplimentaryReward::where('user_id', $duplicate->id)->update(['user_id' => $primary->id]);
            \App\Models\BookingReferral::where('referrer_user_id', $duplicate->id)->update(['referrer_user_id' => $primary->id]);

            $changes = [];
            // Forward-only: if the duplicate had transacted, the merged client is a Client.
            if ($duplicate->customer_status === 'client' && $primary->customer_status !== 'client') {
                $changes['customer_status'] = 'client';
            }
            if ($duplicate->first_transacted_at && (! $primary->first_transacted_at || $duplicate->first_transacted_at < $primary->first_transacted_at)) {
                $changes['first_transacted_at'] = $duplicate->first_transacted_at;
            }
            // Consent is opt-in only — never lose a prior opt-in during a merge.
            if (! $primary->marketing_opt_in && $duplicate->marketing_opt_in) {
                $changes['marketing_opt_in'] = true;
                $changes['marketing_opt_in_at'] = $duplicate->marketing_opt_in_at ?? now();
            }
            if (! $primary->mobile && $duplicate->mobile) {
                $changes['mobile'] = $duplicate->mobile;
            }
            if (! $primary->name && $duplicate->name) {
                $changes['name'] = $duplicate->name;
            }
            if (! $primary->first_name && $duplicate->first_name) {
                $changes['first_name'] = $duplicate->first_name;
            }
            if (! $primary->last_name && $duplicate->last_name) {
                $changes['last_name'] = $duplicate->last_name;
            }
            if ($changes) {
                $primary->forceFill($changes)->save();
            }

            // Retire the duplicate; SoftDeletes removes it from all customer queries.
            $duplicate->delete();

            return $primary->fresh();
        });
    }

    protected function createCustomer(?string $email, ?string $phone, ?string $name, array $contact, ?string $source, bool $declined): User
    {
        $attributes = [
            'user_role_id' => $this->clientRoleId(),
            'email' => $email,
            'mobile' => $phone,
            'name' => $name ?: ($declined ? 'Walk-in (no contact)' : null),
            'first_name' => $this->cleanString($contact['first_name'] ?? null),
            'last_name' => $this->cleanString($contact['last_name'] ?? null),
            'has_account' => false,
            'customer_status' => 'lead',
            'contact_declined' => $declined,
            'marketing_opt_in' => (bool) ($contact['marketing_opt_in'] ?? false),
            'marketing_opt_in_at' => ! empty($contact['marketing_opt_in']) ? now() : null,
            'status' => 'active',
            'registration_source' => $this->mapSource($source),
            'unsubscribe_token' => Str::random(48),
        ];

        try {
            $user = new User();
            $user->forceFill($attributes)->save();

            return $user;
        } catch (\Illuminate\Database\QueryException $e) {
            if ($email && $existing = User::where('email', $email)->first()) {
                return $existing;
            }
            throw $e;
        }
    }

    protected function fillMissingContact(User $user, ?string $name, ?string $phone, array $contact): void
    {
        $changes = [];
        if (! $user->mobile && $phone) {
            $changes['mobile'] = $phone;
        }
        if (! $user->name && $name) {
            $changes['name'] = $name;
        }
        if (! $user->first_name && ! empty($contact['first_name'])) {
            $changes['first_name'] = $this->cleanString($contact['first_name']);
        }
        if (! $user->last_name && ! empty($contact['last_name'])) {
            $changes['last_name'] = $this->cleanString($contact['last_name']);
        }
        if ($changes) {
            $user->forceFill($changes)->save();
        }
    }

    public function applyMarketingConsent(User $user, array $contact): void
    {
        if (! empty($contact['marketing_opt_in']) && ! $user->marketing_opt_in) {
            $user->forceFill([
                'marketing_opt_in' => true,
                'marketing_opt_in_at' => now(),
            ])->save();
        }
    }

    protected function resolveName(array $contact): ?string
    {
        $name = $this->cleanString($contact['name'] ?? null);
        if ($name) {
            return $name;
        }
        $full = trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));

        return $full !== '' ? $full : null;
    }

    protected function mapSource(?string $source): string
    {
        $allowed = ['online', 'walk_in', 'offline', 'booking', 'manual'];

        return in_array($source, $allowed, true) ? $source : 'online';
    }

    protected function cleanString(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' ? $value : null;
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;

/**
 * Gatekeeper for the marketing email stream (stream 3): offers, promotions,
 * "spend more to unlock", win-back. Only customers who opted in receive these,
 * and every marketing email must carry an unsubscribe link.
 *
 * Transactional/appointment (stream 1) and loyalty/reward (stream 2) emails do
 * NOT go through here — they send to all customers regardless of consent.
 */
class MarketingDispatcher
{
    public function allowedFor(User $customer): bool
    {
        return $customer->email && $customer->marketing_opt_in;
    }

    public function send(User $customer, Mailable $mail): bool
    {
        if (! $this->allowedFor($customer)) {
            return false;
        }

        Mail::to($customer->email)->queue($mail);

        return true;
    }

    public function unsubscribeUrl(User $customer): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return $base . '/unsubscribe/' . $customer->unsubscribe_token;
    }
}

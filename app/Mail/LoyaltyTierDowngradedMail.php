<?php

namespace App\Mail;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoyaltyTierDowngradedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?Referral $tier,
        public ?Referral $firstTier = null,
    ) {}

    public function build(): self
    {
        $subject = $this->tier
            ? 'Your discount level has changed to ' . $this->tier->name
            : 'Your discount level has been reset';

        return $this->subject($subject)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.loyalty-tier-downgraded')
            ->with([
                'userName' => $this->user->name ?? 'there',
                'tierName' => $this->tier?->name,
                'tierValue' => $this->tier?->value,
                'hasTier' => $this->tier !== null,
                'firstTierName' => $this->firstTier?->name,
                'firstTierThreshold' => $this->firstTier?->visit_threshold,
                'bookingUrl' => config('app.frontend_url', 'https://uaedevelop.pro') . '/en/booking',
            ]);
    }
}

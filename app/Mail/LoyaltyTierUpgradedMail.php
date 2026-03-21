<?php

namespace App\Mail;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LoyaltyTierUpgradedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Referral $tier,
    ) {}

    public function build(): self
    {
        return $this->subject('Congratulations! You\'ve reached ' . $this->tier->name . ' level!')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.loyalty-tier-upgraded')
            ->with([
                'userName' => $this->user->name ?? 'there',
                'tierName' => $this->tier->name,
                'tierValue' => $this->tier->value,
                'bookingUrl' => config('app.frontend_url', 'https://uaedevelop.pro') . '/en/booking',
            ]);
    }
}

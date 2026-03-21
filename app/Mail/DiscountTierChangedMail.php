<?php

namespace App\Mail;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DiscountTierChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Referral $tier,
    ) {}

    public function build(): self
    {
        return $this->subject('Your discount level has been updated!')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.discount-tier-changed')
            ->with([
                'userName' => $this->user->name ?? 'there',
                'tierName' => $this->tier->name,
                'tierValue' => $this->tier->value,
                'bookingUrl' => config('app.frontend_url', 'https://uaedevelop.pro') . '/en/booking',
            ]);
    }
}

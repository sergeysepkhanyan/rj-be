<?php

namespace App\Mail;

use App\Models\ComplimentaryReward;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComplimentaryGiftExpiringMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ComplimentaryReward $reward,
    ) {}

    public function build(): self
    {
        $this->reward->loadMissing('subService');
        $serviceName = $this->reward->subService?->name ?? 'service';

        return $this->subject('Your complimentary ' . $serviceName . ' gift is expiring soon!')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.complimentary-gift-expiring')
            ->with([
                'userName' => $this->user->name ?? 'there',
                'serviceName' => $serviceName,
                'expiresAt' => $this->reward->expires_at?->format('F j, Y'),
                'bookingUrl' => config('app.frontend_url', 'https://uaedevelop.pro') . '/en/booking-appointment',
            ]);
    }
}

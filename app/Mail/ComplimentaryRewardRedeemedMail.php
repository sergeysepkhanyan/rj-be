<?php

namespace App\Mail;

use App\Models\ComplimentaryReward;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComplimentaryRewardRedeemedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ComplimentaryReward $reward,
    ) {}

    public function build(): self
    {
        $reward = $this->reward->loadMissing(['subService', 'subServiceItem']);

        return $this->subject('Your complimentary reward has been redeemed')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.complimentary-reward-redeemed')
            ->with([
                'userName' => $this->user->name
                    ?? trim(($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? '')) ?: 'there',
                'serviceName' => $reward->resolveServiceName() ?? 'Complimentary Service',
                'redeemedAt' => optional($reward->redeemed_at)->format('Y-m-d'),
            ]);
    }
}

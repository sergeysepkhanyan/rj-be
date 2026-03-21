<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ReferralRewardEarnedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Collection $rewards,
    ) {}

    public function build(): ReferralRewardEarnedMail
    {
        $rewards = $this->rewards->load('subService');

        $rewardsList = $rewards->map(function ($reward) {
            return [
                'id' => $reward->id,
                'serviceName' => $reward->subService?->name ?? 'Complimentary Service',
            ];
        });

        $userName = $this->user->name ?? trim(($this->user->first_name ?? '') . ' ' . ($this->user->last_name ?? '')) ?: 'Valued Customer';

        return $this->subject('You earned a complimentary reward!')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.referral-reward-earned')
            ->with([
                'userName' => $userName,
                'rewards' => $rewardsList,
            ]);
    }
}

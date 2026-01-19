<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminAccessEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $password,
    ) {}

    public function build(): static
    {
        $frontendUrl = config('app.frontend_url', env('FRONTEND_URL'));
        $actionUrl   = rtrim($frontendUrl, '/');

        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo('admin@rjbeautylounge.com', 'Romeo & Juliet Beauty Lounge')
            ->subject('Admin access for Romeo & Juliet Beauty Lounge')
            ->withSymfonyMessage(function ($message) {
                $headers = $message->getHeaders();
                $headers->addIdHeader('Message-ID', uniqid('', true) . '@rjbeautylounge.com');
                $headers->addTextHeader('List-Unsubscribe', '<mailto:admin@rjbeautylounge.com>');
            })
            ->markdown('emails.admin_access', [
                'user'      => $this->user,
                'password'  => $this->password,
                'actionUrl' => $actionUrl,
            ]);
    }
}



<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MarketerAccessEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $password,
        public ?string $accessLink = null,
    ) {}

    public function build(): static
    {
        $actionUrl = $this->accessLink
            ?: rtrim(config('app.frontend_url', env('FRONTEND_URL')), '/') . '/dashboard';

        return $this
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->replyTo('admin@rjbeautylounge.com', 'Romeo & Juliet Beauty Lounge')
            ->subject('Marketer access for Romeo & Juliet Beauty Lounge')
            ->withSymfonyMessage(function ($message) {
                $headers = $message->getHeaders();
                $headers->addIdHeader('Message-ID', uniqid('', true) . '@rjbeautylounge.com');
                $headers->addTextHeader('List-Unsubscribe', '<mailto:admin@rjbeautylounge.com>');
            })
            ->view('emails.marketer_access', [
                'user'      => $this->user,
                'password'  => $this->password,
                'actionUrl' => $actionUrl,
            ]);
    }
}

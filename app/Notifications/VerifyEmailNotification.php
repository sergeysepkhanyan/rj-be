<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id' => $notifiable->getKey(),
                'hash' => sha1($notifiable->getEmailForVerification()),
            ]
        );

        return (new MailMessage)
            ->subject('Verify your email for HHT')
            ->greeting('Hi ' . ($notifiable->name ?? 'there') . ' 👋')
            ->line('Thanks for registering. Please verify your email to continue.')
            ->action('Verify Email', $url)
            ->line('This link expires in 60 minutes.')
            ->salutation('— RJ Team');
    }
}


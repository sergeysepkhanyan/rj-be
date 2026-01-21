<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $token) {}

    public function build(): self
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL')), '/');
        $resetUrl = $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($this->user->email);

        return $this->subject('Reset Your Password')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.reset-password')
            ->text('emails.reset-password-text')
            ->with([
                'name' => $this->user->name,
                'email' => $this->user->email,
                'resetUrl' => $resetUrl,
            ]);
    }
}

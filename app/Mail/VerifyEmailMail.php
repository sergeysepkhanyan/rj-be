<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class VerifyEmailMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public ?string $redirectTo = null,
    ) {}

    public function build(): self
    {
        $backendUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $this->user->getKey(),
                'hash' => sha1($this->user->getEmailForVerification()),
            ]
        );

        $frontendBase = rtrim((string) config('app.frontend_url'), '/');

        $frontendUrl = $frontendBase . '/verify-email?url=' . urlencode($backendUrl);
        
        if ($this->redirectTo) {
            $frontendUrl .= '&redirect_to=' . urlencode($this->redirectTo);
        }

        return $this->subject('Welcome to RJ – confirm your account')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.verify-email')
            ->text('emails.verify-email-text')
            ->with([
                'name'      => $this->user->name,
                'verifyUrl' => $frontendUrl,
            ]);
    }
}



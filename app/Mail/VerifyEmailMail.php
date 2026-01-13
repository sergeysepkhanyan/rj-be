<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;

class VerifyEmailMail extends Mailable
{
    public function __construct(public User $user) {}

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

        $frontendUrl = rtrim(config('app.frontend_url'), '/')
            . '/verify-email?url=' . urlencode($backendUrl);

        return $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject('Verify your email for RJ')
            ->view('emails.verify-email')
            ->with([
                'name'      => $this->user->name,
                'verifyUrl' => $frontendUrl,
            ]);
    }
}



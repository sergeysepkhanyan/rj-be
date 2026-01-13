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
        // Signed backend verification URL (60 min)
        $backendUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            [
                'id'   => $this->user->getKey(),
                'hash' => sha1($this->user->getEmailForVerification()),
            ]
        );

        return $this->subject('Verify your email for RJ')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.verify-email')
            ->text('emails.verify-email-text')
            ->with([
                'name'      => $this->user->name,
                'verifyUrl' => $backendUrl,
            ]);
    }
}



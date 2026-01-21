<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ResetPasswordMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $token) {}

    public function build(): self
    {
        try {
            $frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL')), '/');
            $resetUrl = $frontendUrl . '/reset-password?token=' . $this->token . '&email=' . urlencode($this->user->email);

            Log::info('ResetPasswordMail building', [
                'user_id' => $this->user->id,
                'email' => $this->user->email,
                'token_preview' => substr($this->token, 0, 10) . '...',
                'reset_url' => $resetUrl,
                'from_address' => config('mail.from.address'),
            ]);

            return $this->subject('Update your account access')
                ->from(config('mail.from.address'), config('mail.from.name'))
                ->view('emails.reset-password')
                ->text('emails.reset-password-text')
                ->with([
                    'name' => $this->user->name,
                    'email' => $this->user->email,
                    'resetUrl' => $resetUrl,
                ]);
        } catch (Throwable $e) {
            Log::error('ResetPasswordMail build failed', [
                'user_id' => $this->user->id ?? null,
                'email' => $this->user->email ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ResetPasswordMail job failed', [
            'user_id' => $this->user->id ?? null,
            'email' => $this->user->email ?? null,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}

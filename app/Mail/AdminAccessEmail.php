<?php

// app/Mail/AdminWelcomeMail.php
namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminAccessEmail extends Mailable
{
    use Queueable, SerializesModels;

    public User $user;
    public string $password;

    public function __construct(User $user, string $password)
    {
        $this->user = $user;
        $this->password = $password;
    }

    public function build()
    {
        return $this->subject('Administrator Access Granted — Romeo & Juliet Beauty Lounge')
            ->markdown('emails.admin_access', [
                'user' => $this->user,
                'password' => $this->password,
                'actionUrl' => 'http://localhost:3000/admin/login'
            ]);
    }
}


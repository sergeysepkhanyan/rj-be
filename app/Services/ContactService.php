<?php

namespace App\Services;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use App\Repositories\Interfaces\ContactMessageRepositoryInterface;
use Illuminate\Support\Facades\Mail;

class ContactService
{
    public function __construct(
        private readonly ContactMessageRepositoryInterface $repo
    ) {}

    public function submit(array $payload, ?string $ip, ?string $userAgent): ContactMessage
    {
        $message = $this->repo->create([
            'name'       => $payload['name'],
            'email'      => $payload['email'],
            'phone'      => $payload['phone'] ?? null,
            'message'    => $payload['message'],
            'ip'         => $ip,
            'user_agent' => $userAgent ? substr($userAgent, 0, 500) : null,
        ]);

        Mail::to(env('MAIL_FROM_ADDRESS'))->send(new ContactMessageReceived($message));

        return $this->repo->markEmailed($message);
    }
}


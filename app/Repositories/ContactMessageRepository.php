<?php

namespace App\Repositories;


use App\Models\ContactMessage;
use App\Repositories\Interfaces\ContactMessageRepositoryInterface;

class ContactMessageRepository implements ContactMessageRepositoryInterface
{
    public function create(array $data): ContactMessage
    {
        return ContactMessage::create($data);
    }

    public function markEmailed(ContactMessage $message): ContactMessage
    {
        $message->forceFill(['emailed_at' => now()])->save();

        return $message->refresh();
    }
}

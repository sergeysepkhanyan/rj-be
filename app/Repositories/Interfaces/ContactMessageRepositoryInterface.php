<?php
namespace App\Repositories\Interfaces;

use App\Models\ContactMessage;

interface ContactMessageRepositoryInterface
{
    public function create(array $data): ContactMessage;

    public function markEmailed(ContactMessage $message): ContactMessage;
}


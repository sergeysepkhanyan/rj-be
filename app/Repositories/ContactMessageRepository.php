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

    public function paginate(array $filters, int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $q = ContactMessage::query()->latest('id');

        if (!empty($filters['search'])) {
            $s = $filters['search'];
            $q->where(function ($qq) use ($s) {
                $qq->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('message', 'like', "%{$s}%");
            });
        }

        if (!empty($filters['from'])) {
            $q->whereDate('created_at', '>=', $filters['from']);
        }

        if (!empty($filters['to'])) {
            $q->whereDate('created_at', '<=', $filters['to']);
        }

        if (isset($filters['unread']) && (int)$filters['unread'] === 1) {
            $q->whereNull('read_at');
        }

        return $q->paginate($perPage)->withQueryString();
    }
}

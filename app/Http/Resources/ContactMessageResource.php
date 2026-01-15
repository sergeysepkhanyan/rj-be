<?php

namespace App\Http\Resources;

class ContactMessageResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'message' => $data['message'] ?? null,
            'ip' => $data['ip'] ?? null,
            'userAgent' => $data['user_agent'] ?? null,
            'emailedAt' => $data['emailed_at'] ?? null,
            'readAt' => $data['read_at'] ?? null,
            'repliedAt' => $data['replied_at'] ?? null,
            'createdAt' => $data['created_at'] ?? null
        ];
    }
}

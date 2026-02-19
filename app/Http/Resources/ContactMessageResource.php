<?php

namespace App\Http\Resources;

use App\Models\User;

class ContactMessageResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        // Check if email matches a registered client
        $client = null;
        if (!empty($data['email'])) {
            $user = User::where('email', $data['email'])
                ->whereHas('role', function ($q) {
                    $q->where('slug', 'client');
                })
                ->first();

            if ($user) {
                $client = [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ];
            }
        }

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
            'createdAt' => $data['created_at'] ?? null,
            'client' => $client,
        ];
    }
}

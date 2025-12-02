<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $email
 * @property mixed $mobile
 * @property mixed $date_of_birth
 * @property mixed $id
 * @property mixed $subservices
 * @property mixed $image
 * @method relationLoaded(string $string)
 */
class UserResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'dateOfBirth' => $data['date_of_birth'] ?? null,
            'role' => $this->role->name ?? null,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
        ];
    }
}


<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $name
 * @property mixed $email
 * @property mixed $mobile
 * @property mixed $date_of_birth
 */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'name' => $this->name ?? null,
            'email' => $this->email ?? null,
            'mobile' => $this->mobile ?? null,
            'date_of_birth' => $this->date_of_birth ?? null,
        ];
    }
}


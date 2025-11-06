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
 * @method relationLoaded(string $string)
 */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? null,
            'email' => $this->email ?? null,
            'mobile' => $this->mobile ?? null,
            'date_of_birth' => $this->date_of_birth ?? null,
            'role' => $this->role->name ?? null,
            'subservices' => $this->when(
                $this->relationLoaded('subservices') || $this->subservices,
                SubServiceResource::collection($this->subservices)
            ),
        ];
    }
}


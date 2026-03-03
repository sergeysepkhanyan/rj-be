<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'nameAr' => $this->name_ar,
            'phone' => $this->phone,
            'email' => $this->email,
            'source' => $this->source,
            'status' => $this->status,
            'notes' => $this->notes,
            'referral' => $this->whenLoaded('referral', fn() => new ReferralResource($this->referral)),
            'convertedUser' => $this->whenLoaded('convertedUser', fn() => [
                'id' => $this->convertedUser->id,
                'name' => $this->convertedUser->name,
                'email' => $this->convertedUser->email,
            ]),
            'convertedAt' => $this->converted_at?->toISOString(),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}

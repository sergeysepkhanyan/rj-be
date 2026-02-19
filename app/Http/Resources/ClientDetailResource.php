<?php

namespace App\Http\Resources;

class ClientDetailResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        // Build full name from first_name + last_name if name is empty
        $fullName = $data['name'] ?? null;
        if (empty($fullName)) {
            $firstName = $data['first_name'] ?? '';
            $lastName = $data['last_name'] ?? '';
            $fullName = trim($firstName . ' ' . $lastName) ?: null;
        }

        return [
            'id' => $data['id'] ?? null,
            'name' => $fullName,
            'firstName' => $data['first_name'] ?? null,
            'lastName' => $data['last_name'] ?? null,
            'email' => $data['email'] ?? null,
            'mobile' => $data['mobile'] ?? null,
            'dateOfBirth' => $data['date_of_birth'] ?? null,
            'description' => $data['description'] ?? null,
            'image' => $this->image ? asset('storage/' . $this->image) : null,
            'status' => $data['status'] ?? 'active',
            'isLocked' => ($data['status'] ?? 'active') === 'locked',
            'emailVerifiedAt' => $data['email_verified_at'] ?? null,
            'createdAt' => $data['created_at'] ?? null,
            'referral' => $this->referral ? new ReferralResource($this->referral) : null,
            'manualReferral' => $this->whenLoaded('manualReferral', function () {
                return new ReferralResource($this->manualReferral);
            }),
            'notes' => $this->whenLoaded('notes', function () {
                return $this->notes->map(function ($note) {
                    return [
                        'id' => $note->id,
                        'content' => $note->content,
                        'createdAt' => $note->created_at,
                        'createdBy' => [
                            'id' => $note->createdBy->id,
                            'name' => $note->createdBy->name,
                        ],
                    ];
                });
            }),
        ];
    }
}

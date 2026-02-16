<?php

namespace App\Http\Resources;

class FaqResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);

        return [
            'id' => $data['id'] ?? null,
            'category' => $data['category'] ?? null,
            'categoryAr' => $data['category_ar'] ?? null,
            'question' => $data['question'] ?? null,
            'questionAr' => $data['question_ar'] ?? null,
            'answer' => $data['answer'] ?? null,
            'answerAr' => $data['answer_ar'] ?? null,
            'sortOrder' => $data['sort_order'] ?? 0,
            'isActive' => $data['is_active'] ?? true,
            'createdAt' => $this->created_at ?? null,
            'updatedAt' => $this->updated_at ?? null,
        ];
    }
}

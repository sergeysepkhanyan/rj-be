<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PageSeoResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        
        return [
            'id' => $this->id ?? null,
            'pageKey' => $data['page_key'] ?? $this->page_key ?? null,
            'metaTitle' => $data['meta_title'] ?? $this->meta_title ?? null,
            'metaTitleAr' => $data['meta_title_ar'] ?? $this->meta_title_ar ?? null,
            'metaDescription' => $data['meta_description'] ?? $this->meta_description ?? null,
            'metaDescriptionAr' => $data['meta_description_ar'] ?? $this->meta_description_ar ?? null,
            'keywords' => $data['keywords'] ?? $this->keywords ?? null,
            'keywordsAr' => $data['keywords_ar'] ?? $this->keywords_ar ?? null,
            'ogImage' => $data['og_image'] ?? $this->og_image ?? null,
            'canonicalUrl' => $data['canonical_url'] ?? $this->canonical_url ?? null,
        ];
    }
}

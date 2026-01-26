<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PageSeoResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        
        return [
            'id' => $this->resource->id ?? null,
            'pageKey' => $this->resource->page_key ?? null,
            'metaTitle' => $this->resource->meta_title ?? null,
            'metaTitleAr' => $this->resource->meta_title_ar ?? null,
            'metaDescription' => $this->resource->meta_description ?? null,
            'metaDescriptionAr' => $this->resource->meta_description_ar ?? null,
            'keywords' => $this->resource->keywords ?? null,
            'keywordsAr' => $this->resource->keywords_ar ?? null,
            'ogImage' => $this->resource->og_image ?? null,
            'canonicalUrl' => $this->resource->canonical_url ?? null,
        ];
    }
}

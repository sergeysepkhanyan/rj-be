<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;


/**
 * @property mixed $slug
 * @property mixed $content
 */
class PageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
          $this->slug => $this->content
        ];
    }
}


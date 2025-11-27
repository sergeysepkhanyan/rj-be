<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $id
 * @property mixed $lang
 * @property mixed $author
 * @property mixed $title
 * @property mixed $meta_title
 * @property mixed $meta_description
 * @property mixed $slug
 * @property mixed $preview
 * @property mixed $content
 * @property mixed $image
 * @property mixed $show_author
 * @property mixed $status
 * @property mixed $publish_date
 */
class PostResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'lang'             => $this->lang,
            'author'           => $this->author,
            'title'            => $this->title,
            'meta_title'       => $this->meta_title ?? null,
            'meta_description' => $this->meta_description ?? null,
            'slug'             => $this->slug,
            'preview'          => $this->preview ?? null,
            'content'          => $this->content,
            'image'            => $this->image ? asset('storage/' . $this->image) : null,
            'show_author'      => (bool) $this->show_author,
            'status'           => $this->status,
            'publish_date'     => $this->publish_date
        ];
    }
}


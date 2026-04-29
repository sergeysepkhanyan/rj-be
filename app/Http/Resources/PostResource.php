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
    /**
     * Defensive read for legacy rows whose `image` already contains one or
     * more "http(s)://host/storage/" prefixes (from before the Post model
     * mutator existed). Always returns a plain relative path.
     */
    private static function stripStorageUrl(string $value): string
    {
        while (preg_match('#^https?://[^/]+/storage/(.+)$#i', $value, $m)) {
            $value = $m[1];
        }
        return $value;
    }

    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'lang'             => $this->lang,
            'author'           => $this->author,
            'title'            => $this->title,
            'metaTitle'       => $this->meta_title ?? null,
            'metaDescription' => $this->meta_description ?? null,
            'slug'             => $this->slug,
            'preview'          => $this->preview ?? null,
            'content'          => $this->content,
            'image'            => $this->image ? asset('storage/' . self::stripStorageUrl($this->image)) : null,
            'showAuthor'      => (bool) $this->show_author,
            'status'           => $this->status,
            'publishDate'     => $this->publish_date
        ];
    }
}


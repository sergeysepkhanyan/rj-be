<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @property mixed $slug
 * @property mixed $content
 */
class PageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            $this->slug => $this->transformImages($this->content)
        ];
    }

    protected function transformImages($data): array|string|null
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($this->isImageKey($key)) {
                    $data[$key] = $this->resolveFileUrl($value);
                } else {
                    $data[$key] = $this->transformImages($value);
                }
            }
            return $data;
        }

        if (is_object($data)) {
            foreach ($data as $key => $value) {
                if ($this->isImageKey($key)) {
                    $data->$key = $this->resolveFileUrl($value);
                } else {
                    $data->$key = $this->transformImages($value);
                }
            }
            return $data;
        }

        return $data;
    }

    protected function isImageKey(string $key): bool
    {
        return in_array($key, ['image', 'src', 'backgroundImage'], true);
    }

    protected function resolveFileUrl(?string $value): ?string
    {
        if (empty($value)) {
            return $value;
        }
        if (str_starts_with($value, 'http')) {
            return $value;
        }return Storage::url($value);
    }
}



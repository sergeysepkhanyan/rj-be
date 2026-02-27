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
            $this->slug => $this->transformKeys($this->transformImages($this->content))
        ];
    }

    /**
     * Recursively convert snake_case keys to camelCase
     */
    protected function transformKeys($data): array|string|null
    {
        if (!is_array($data)) {
            return $data;
        }

        $result = [];
        foreach ($data as $key => $value) {
            $camelKey = is_string($key) ? $this->snakeToCamel($key) : $key;
            $result[$camelKey] = is_array($value) ? $this->transformKeys($value) : $value;
        }
        return $result;
    }

    protected function snakeToCamel(string $string): string
    {
        return lcfirst(str_replace('_', '', ucwords($string, '_')));
    }

    protected function transformImages($data): array|string|null
    {
        $base = url('/');

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (in_array($key, ['image', 'src', 'backgroundImage', 'background_image'])) {
                    if (!empty($value) && is_string($value) && !str_starts_with($value, 'http')) {
                        $data[$key] = $this->withStorage($base, $value);
                    }
                } else {
                    $data[$key] = $this->transformImages($value);
                }
            }
            return $data;
        }

        if (is_object($data)) {
            foreach ($data as $key => $value) {
                if (in_array($key, ['image', 'src', 'backgroundImage', 'background_image'])) {
                    if (!empty($value) && is_string($value) && !str_starts_with($value, 'http')) {
                        $data->$key = $this->withStorage($base, $value);
                    }
                } else {
                    $data->$key = $this->transformImages($value);
                }
            }
            return $data;
        }

        return $data;
    }

    protected function withStorage(string $base, string $value): string
    {
        $path = ltrim($value, '/');

        if (str_starts_with($path, 'storage/')) {
            return $base . '/' . $path;
        }

        return $base . '/storage/' . $path;
    }
}

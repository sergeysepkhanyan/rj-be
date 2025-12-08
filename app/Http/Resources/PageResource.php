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
            $this->slug => $this->transformImages($this->content)
        ];
    }


    protected function transformImages($data): array | string | null
    {
        $base = url('/');

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (in_array($key, ['image', 'src', 'backgroundImage'])) {
                    if (!empty($value) && !str_starts_with($value, 'http')) {
                        $data[$key] = $base . '/' . ltrim($value, '/');
                    }
                } else {
                    $data[$key] = $this->transformImages($value);
                }
            }
            return $data;
        }

        if (is_object($data)) {
            foreach ($data as $key => $value) {
                if (in_array($key, ['image', 'src', 'backgroundImage'])) {
                    if (!empty($value) && !str_starts_with($value, 'http')) {
                        $data->$key = $base . '/' . ltrim($value, '/');
                    }
                } else {
                    $data->$key = $this->transformImages($value);
                }
            }
            return $data;
        }

        return $data;
    }

}


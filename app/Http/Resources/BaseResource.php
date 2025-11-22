<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BaseResource extends JsonResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        $locale = app()->bound('api_locale') ? app('api_locale') : 'en';
        if ($locale === 'ar') {
            foreach ($data as $key => $value) {
                $arField = $key . '_ar';
                if (!empty($this->{$arField})) {
                    $data[$key] = $this->{$arField};
                }
            }
        }
        foreach ($data as $key => $value) {
            if ($value instanceof JsonResource) {
                $data[$key] = $value->toArray($request);
            } elseif (is_array($value)) {
                $data[$key] = $this->translateCollection($value, $request);
            }
        }

        return $data;
    }

    private function translateCollection(array $items, $request): array
    {
        return array_map(function ($item) use ($request) {
            if ($item instanceof JsonResource) {
                return $item->toArray($request);
            } elseif (is_array($item)) {
                return $this->translateCollection($item, $request);
            }
            return $item;
        }, $items);
    }
}

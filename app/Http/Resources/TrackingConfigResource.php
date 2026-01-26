<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TrackingConfigResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        
        return [
            'googleAnalyticsId' => $this->resource->google_analytics_id ?? null,
            'googleTagManagerId' => $this->resource->google_tag_manager_id ?? null,
            'facebookPixelId' => $this->resource->facebook_pixel_id ?? null,
            'snapchatPixelId' => $this->resource->snapchat_pixel_id ?? null,
            'customScripts' => $this->whenLoaded('customScripts', function () {
                return $this->customScripts->map(function ($script) {
                    return [
                        'id' => $script->id,
                        'name' => $script->name,
                        'code' => $script->code,
                        'position' => $script->position,
                        'enabled' => $script->enabled,
                    ];
                });
            }),
        ];
    }
}

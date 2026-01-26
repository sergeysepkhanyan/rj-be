<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TrackingConfigResource extends BaseResource
{
    public function toArray($request): array
    {
        $data = parent::toArray($request);
        
        return [
            'googleAnalyticsId' => $data['google_analytics_id'] ?? $this->google_analytics_id ?? null,
            'googleTagManagerId' => $data['google_tag_manager_id'] ?? $this->google_tag_manager_id ?? null,
            'facebookPixelId' => $data['facebook_pixel_id'] ?? $this->facebook_pixel_id ?? null,
            'snapchatPixelId' => $data['snapchat_pixel_id'] ?? $this->snapchat_pixel_id ?? null,
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

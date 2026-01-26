<?php

namespace App\Services;

use App\Models\CustomScript;
use App\Models\TrackingConfig;
use App\Repositories\Interfaces\TrackingConfigRepositoryInterface;
use Illuminate\Support\Facades\DB;

class TrackingConfigService
{
    public function __construct(
        protected TrackingConfigRepositoryInterface $trackingConfigRepository
    ) {}

    public function get(): TrackingConfig
    {
        $config = $this->trackingConfigRepository->findOrCreate();
        $config->load('customScripts');
        return $config;
    }

    public function getPublic(): array
    {
        $config = $this->get();
        $scripts = $config->customScripts()->where('enabled', true)->get();

        $headScripts = [];
        $bodyStartScripts = [];
        $bodyEndScripts = [];

        foreach ($scripts as $script) {
            $code = $script->code;
            match($script->position) {
                'head' => $headScripts[] = $code,
                'body_start' => $bodyStartScripts[] = $code,
                'body_end' => $bodyEndScripts[] = $code,
            };
        }

        return [
            'google_analytics_id' => $config->google_analytics_id,
            'google_tag_manager_id' => $config->google_tag_manager_id,
            'facebook_pixel_id' => $config->facebook_pixel_id,
            'snapchat_pixel_id' => $config->snapchat_pixel_id,
            'head_scripts' => $headScripts,
            'body_start_scripts' => $bodyStartScripts,
            'body_end_scripts' => $bodyEndScripts,
        ];
    }

    public function update(array $data): TrackingConfig
    {
        return DB::transaction(function () use ($data) {
            $config = $this->trackingConfigRepository->findOrCreate();

            $configData = array_filter([
                'google_analytics_id' => $data['google_analytics_id'] ?? null,
                'google_tag_manager_id' => $data['google_tag_manager_id'] ?? null,
                'facebook_pixel_id' => $data['facebook_pixel_id'] ?? null,
                'snapchat_pixel_id' => $data['snapchat_pixel_id'] ?? null,
            ], fn($value) => $value !== null);

            if (!empty($configData)) {
                $this->trackingConfigRepository->update($config, $configData);
            }

            if (isset($data['custom_scripts']) && is_array($data['custom_scripts'])) {
                $this->syncCustomScripts($config, $data['custom_scripts']);
            }

            return $config->fresh('customScripts');
        });
    }

    protected function syncCustomScripts(TrackingConfig $config, array $scripts): void
    {
        $existingIds = collect($scripts)->pluck('id')->filter()->toArray();
        
        $config->customScripts()->whereNotIn('id', $existingIds)->delete();

        foreach ($scripts as $scriptData) {
            $id = $scriptData['id'] ?? null;
            $isDelete = $scriptData['_delete'] ?? false;

            if ($isDelete && $id) {
                CustomScript::where('id', $id)->delete();
                continue;
            }

            $scriptData['tracking_config_id'] = $config->id;
            unset($scriptData['id'], $scriptData['_delete']);

            if ($id) {
                CustomScript::where('id', $id)->update($scriptData);
            } else {
                CustomScript::create($scriptData);
            }
        }
    }
}

<?php

namespace App\Repositories;

use App\Models\TrackingConfig;
use App\Repositories\Interfaces\TrackingConfigRepositoryInterface;

class TrackingConfigRepository implements TrackingConfigRepositoryInterface
{
    public function findOrCreate(): TrackingConfig
    {
        $config = TrackingConfig::find(1);
        
        if (!$config) {
            $config = TrackingConfig::create(['id' => 1]);
        }
        
        return $config;
    }

    public function update(TrackingConfig $config, array $data): TrackingConfig
    {
        $config->update($data);
        return $config->fresh();
    }
}

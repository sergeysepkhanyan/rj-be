<?php

namespace App\Repositories\Interfaces;

use App\Models\TrackingConfig;

interface TrackingConfigRepositoryInterface
{
    public function findOrCreate(): TrackingConfig;
    public function update(TrackingConfig $config, array $data): TrackingConfig;
}

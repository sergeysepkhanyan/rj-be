<?php

namespace Database\Seeders;

use App\Models\TrackingConfig;
use Illuminate\Database\Seeder;

class TrackingConfigSeeder extends Seeder
{
    public function run(): void
    {
        TrackingConfig::firstOrCreate(['id' => 1]);
    }
}

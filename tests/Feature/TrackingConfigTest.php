<?php

namespace Tests\Feature;

use App\Models\TrackingConfig;
use Tests\TestCase;

class TrackingConfigTest extends TestCase
{
    public function test_admin_can_view_tracking_config(): void
    {
        $this->actingAsAdmin();

        TrackingConfig::create(['id' => 1]);

        $response = $this->getJson('/api/admin/tracking-config');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => ['googleAnalyticsId', 'googleTagManagerId'],
            ]);
    }

    public function test_admin_can_update_tracking_config(): void
    {
        $this->actingAsAdmin();

        TrackingConfig::create(['id' => 1]);

        $response = $this->putJson('/api/admin/tracking-config', [
            'googleAnalyticsId' => 'UA-123456',
            'googleTagManagerId' => 'GTM-ABC123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.googleAnalyticsId', 'UA-123456');
    }

    public function test_public_tracking_config_endpoint(): void
    {
        // The public endpoint reads the singleton config at id=1 (findOrCreate → find(1)).
        // `id` is not mass-assignable, so set it explicitly — otherwise the row lands on
        // the auto-increment id (which transaction rollback doesn't reset), find(1) misses,
        // and an empty config is returned.
        $config = new TrackingConfig(['google_analytics_id' => 'UA-123456']);
        $config->id = 1;
        $config->save();

        $response = $this->getJson('/api/tracking-config/public');

        $response->assertStatus(200)
            ->assertJsonPath('data.google_analytics_id', 'UA-123456');
    }
}

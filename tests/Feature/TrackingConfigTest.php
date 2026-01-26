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
        TrackingConfig::create(['id' => 1, 'google_analytics_id' => 'UA-123456']);

        $response = $this->getJson('/api/tracking-config/public');

        $response->assertStatus(200)
            ->assertJsonPath('data.google_analytics_id', 'UA-123456');
    }
}

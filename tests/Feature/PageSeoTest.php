<?php

namespace Tests\Feature;

use App\Models\PageSeo;
use App\Models\UserRole;
use Tests\TestCase;

class PageSeoTest extends TestCase
{
    public function test_admin_can_view_all_page_seo(): void
    {
        $this->actingAsAdmin();
        
        PageSeo::create(['page_key' => 'homepage']);
        PageSeo::create(['page_key' => 'about']);

        $response = $this->getJson('/api/admin/page-seo');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'homepage' => ['id', 'pageKey'],
                    'about' => ['id', 'pageKey'],
                ],
            ]);
    }

    public function test_admin_can_update_page_seo(): void
    {
        $this->actingAsAdmin();
        
        PageSeo::create(['page_key' => 'homepage']);

        $response = $this->putJson('/api/admin/page-seo/homepage', [
            'metaTitle' => 'Home Page Title',
            'metaDescription' => 'Home page description',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.metaTitle', 'Home Page Title');
    }

    public function test_marketer_can_access_page_seo(): void
    {
        $this->actingAsMarketer();

        $response = $this->getJson('/api/admin/page-seo');

        $response->assertStatus(200);
    }
}

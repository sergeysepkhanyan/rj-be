<?php

namespace Database\Seeders;

use App\Models\PageSeo;
use Illuminate\Database\Seeder;

class PageSeoSeeder extends Seeder
{
    public function run(): void
    {
        $pages = ['homepage', 'about', 'contact', 'store', 'services', 'blog'];

        foreach ($pages as $pageKey) {
            PageSeo::firstOrCreate(
                ['page_key' => $pageKey],
                ['page_key' => $pageKey]
            );
        }
    }
}

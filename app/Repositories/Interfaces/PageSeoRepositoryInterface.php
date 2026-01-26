<?php

namespace App\Repositories\Interfaces;

use App\Models\PageSeo;

interface PageSeoRepositoryInterface
{
    public function all();
    public function findByKey(string $pageKey): ?PageSeo;
    public function create(array $data): PageSeo;
    public function update(PageSeo $pageSeo, array $data): PageSeo;
}

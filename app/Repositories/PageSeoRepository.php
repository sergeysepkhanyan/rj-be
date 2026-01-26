<?php

namespace App\Repositories;

use App\Models\PageSeo;
use App\Repositories\Interfaces\PageSeoRepositoryInterface;

class PageSeoRepository implements PageSeoRepositoryInterface
{
    public function all()
    {
        return PageSeo::all();
    }

    public function findByKey(string $pageKey): ?PageSeo
    {
        return PageSeo::where('page_key', $pageKey)->first();
    }

    public function create(array $data): PageSeo
    {
        return PageSeo::create($data);
    }

    public function update(PageSeo $pageSeo, array $data): PageSeo
    {
        $pageSeo->update($data);
        return $pageSeo->fresh();
    }
}

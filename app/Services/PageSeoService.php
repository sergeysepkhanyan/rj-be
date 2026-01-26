<?php

namespace App\Services;

use App\Models\PageSeo;
use App\Repositories\Interfaces\PageSeoRepositoryInterface;

class PageSeoService
{
    public function __construct(
        protected PageSeoRepositoryInterface $pageSeoRepository
    ) {}

    public function getAll(): array
    {
        $pages = $this->pageSeoRepository->all();
        $result = [];
        foreach ($pages as $page) {
            $resource = new \App\Http\Resources\PageSeoResource($page);
            $result[$page->page_key] = $resource->toArray(\Illuminate\Http\Request::create('/'));
        }
        return $result;
    }

    public function getByKey(string $pageKey): ?PageSeo
    {
        return $this->pageSeoRepository->findByKey($pageKey);
    }

    public function updateByKey(string $pageKey, array $data): PageSeo
    {
        $pageSeo = $this->pageSeoRepository->findByKey($pageKey);
        
        if (!$pageSeo) {
            $data['page_key'] = $pageKey;
            return $this->pageSeoRepository->create($data);
        }

        return $this->pageSeoRepository->update($pageSeo, $data);
    }
}

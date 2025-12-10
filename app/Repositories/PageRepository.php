<?php

namespace App\Repositories;

use App\Models\Page;
use App\Repositories\Interfaces\PageRepositoryInterface;
class PageRepository implements PageRepositoryInterface
{
    public function all()
    {
        return Page::all();
    }

    public function find($id)
    {
        return Page::findOrFail($id);
    }

    public function findBySlug(string $slug)
    {
        return Page::where('slug', $slug)->first();
    }

    public function create(array $data)
    {
        return Page::create($data);
    }

    public function update(Page $page, array $data): Page
    {
        $page->update($data);
        return $page;
    }
}


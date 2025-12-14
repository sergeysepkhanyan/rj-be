<?php

namespace App\Services;

use App\Models\Weekday;
use App\Repositories\Interfaces\PageRepositoryInterface;

class PageService
{
    protected PageRepositoryInterface $pageRepository;

    public function __construct(
        PageRepositoryInterface $pageRepository,
    )
    {
        $this->pageRepository = $pageRepository;
    }

    public function getAllPages()
    {
        return $this->pageRepository->all();
    }

    public function create(array $data)
    {
        return $this->pageRepository->create($data);
    }

    public function update(array $data)
    {
        $slug = array_key_first($data);
        $page = $this->pageRepository->findBySlug($slug);
        return $this->pageRepository->update($page, [
            'content' => $data[$slug]
        ]);
    }
}


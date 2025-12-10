<?php

namespace App\Repositories\Interfaces;

use App\Models\Page;

interface PageRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(Page $page, array $data): Page;
}

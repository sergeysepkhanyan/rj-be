<?php

namespace App\Repositories\Interfaces;

use App\Models\Category;

interface CategoryRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(Category $category, array $data): Category;
    public function delete(Category $category);
}

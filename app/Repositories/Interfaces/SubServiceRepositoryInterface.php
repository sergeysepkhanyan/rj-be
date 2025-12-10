<?php

namespace App\Repositories\Interfaces;

use App\Models\SubService;

interface SubServiceRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(SubService $subService, array $data): SubService;
    public function delete(SubService $subService);
}

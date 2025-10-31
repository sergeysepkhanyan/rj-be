<?php

namespace App\Repositories;

use App\Models\SubService;
use App\Repositories\Interfaces\SubServiceRepositoryInterface;

class SubServiceManagerRepository implements SubServiceRepositoryInterface
{
    public function all()
    {
        return SubService::all();
    }

    public function find($id)
    {
        return SubService::findOrFail($id);
    }

    public function create(array $data)
    {
        return SubService::create($data);
    }

    public function update(SubService $subService, array $data): SubService
    {
        $subService->update($data);
        return $subService;
    }

    public function delete($id)
    {
        $subService = SubService::findOrFail($id);
        return $subService->delete();
    }
}

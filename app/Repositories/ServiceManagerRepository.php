<?php

namespace App\Repositories;

use App\Filters\ServiceFilter;
use App\Models\Service;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ServiceManagerRepository implements ServiceRepositoryInterface
{
    public function all()
    {
        return Service::all();
    }

    public function find($id)
    {
        return Service::findOrFail($id);
    }

    public function create(array $data)
    {
        return Service::create($data);
    }

    public function update($id, array $data)
    {
        $service = Service::findOrFail($id);
        $service->update($data);
        $service->load('subServices.items.variants');
        return $service;
    }

    public function delete($id)
    {
        $service = Service::findOrFail($id);
        return $service->delete();
    }

    public function paginateWithFilter(?ServiceFilter $filter = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = Service::query();

        if ($filter) {
            $query = $filter->apply($query);
        }

        return $query->paginate($perPage);
    }

}

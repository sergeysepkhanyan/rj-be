<?php

namespace App\Repositories;

use App\Filters\ServiceFilter;
use App\Models\Service;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ServiceManagerRepository implements ServiceRepositoryInterface
{
    public function all(array $filters = []): \Illuminate\Database\Eloquent\Collection
    {
        return Service::query()->when(!empty($filters['category_id']), function ($query) use ($filters) {
            $query->where('category_id', (int) $filters['category_id']);
        })->get();
    }

    public function find($id)
    {
        return Service::findOrFail($id);
    }

    public function create(array $data)
    {
        return Service::create($data);
    }

    public function update(Service $service, array $data): Service
    {
        $service->update($data);
        $service->load('subServices.items');
        return $service;
    }

    public function delete(Service $service): ?bool
    {
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

    public function getByIds(array $services): \Illuminate\Database\Eloquent\Collection
    {
        $query = Service::query()->whereIn('id', $services)->with('subServices.items', 'category');

        return $query->get();
    }

}

<?php

namespace App\Repositories;

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

    public function paginateWithSearch(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        $query = Service::with('subServices.items.variants');
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");

                $q->orWhereHas('subServices', function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
                $q->orWhereHas('subServices.items', function ($item) use ($search) {
                    $item->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
//
//                $q->orWhereHas('subServices.items.variants', function ($variant) use ($search) {
//                    $variant->where('name', 'like', "%{$search}%")
//                        ->orWhere('description', 'like', "%{$search}%");
//                });
            });
        }
        return $query->paginate($perPage);
    }
}

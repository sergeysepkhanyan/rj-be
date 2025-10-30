<?php

namespace App\Repositories;

use App\Models\Service;
use App\Repositories\Interfaces\ServiceRepositoryInterface;

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
}

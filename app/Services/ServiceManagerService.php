<?php

namespace App\Services;

use App\Filters\ServiceFilter;
use App\Models\Service;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ServiceManagerService
{
    public function __construct(protected ServiceRepositoryInterface $serviceRepository){}

    public function getAllServices( array $filters = [] )
    {
        return $this->serviceRepository->all($filters);
    }

    public function getServiceById($id)
    {
        return $this->serviceRepository->find($id);
    }

    public function createService(array $data)
    {
        return $this->serviceRepository->create($data);
    }

    public function updateService(Service $service, array $data): Service
    {
        return $this->serviceRepository->update($service, $data);
    }

    public function deleteService(Service $service): ?bool
    {
        return $this->serviceRepository->delete($service);
    }

    public function getPaginatedServices(?ServiceFilter $filter = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->serviceRepository->paginateWithFilter($filter, $perPage);
    }

    public function getByIds(array $services): \Illuminate\Database\Eloquent\Collection
    {
        return $this->serviceRepository->getByIds($services);
    }

}

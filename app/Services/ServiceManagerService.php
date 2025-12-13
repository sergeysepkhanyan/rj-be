<?php

namespace App\Services;

use App\Filters\ServiceFilter;
use App\Models\Service;
use App\Repositories\Interfaces\ServiceRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class ServiceManagerService
{
    protected ServiceRepositoryInterface $serviceRepository;

    public function __construct(ServiceRepositoryInterface $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    public function getAllServices()
    {
        return $this->serviceRepository->all();
    }

    public function getServiceById($id)
    {
        return $this->serviceRepository->find($id);
    }

    public function createService(array $data)
    {
        return $this->serviceRepository->create($data);
    }

    public function updateService($id, array $data): Service
    {
        return $this->serviceRepository->update($id, $data);
    }

    public function deleteService(Service $service): ?bool
    {
        return $this->serviceRepository->delete($service);
    }

    public function getPaginatedServices(?ServiceFilter $filter = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->serviceRepository->paginateWithFilter($filter, $perPage);
    }

}

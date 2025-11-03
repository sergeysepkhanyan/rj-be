<?php

namespace App\Services;

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

    public function updateService($id, array $data)
    {
        return $this->serviceRepository->update($id, $data);
    }

    public function deleteService($id)
    {
        return $this->serviceRepository->delete($id);
    }

    public function getPaginatedServices(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->serviceRepository->paginateWithSearch($search, $perPage);
    }
}

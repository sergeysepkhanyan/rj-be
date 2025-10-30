<?php

namespace App\Services;

use App\Repositories\Interfaces\SubServiceRepositoryInterface;

class SubServiceManagerService
{
    protected SubServiceRepositoryInterface $subServiceRepository;

    public function __construct(SubServiceRepositoryInterface $subServiceRepository)
    {
        $this->subServiceRepository = $subServiceRepository;
    }

    public function getAllSubServices()
    {
        return $this->subServiceRepository->all();
    }

    public function getSubServiceById($id)
    {
        return $this->subServiceRepository->find($id);
    }

    public function createSubService(array $data)
    {
        return $this->subServiceRepository->create($data);
    }

    public function updateSubService($id, array $data)
    {
        return $this->subServiceRepository->update($id, $data);
    }

    public function deleteSubService($id)
    {
        return $this->subServiceRepository->delete($id);
    }
}

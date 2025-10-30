<?php

namespace App\Services;

use App\Repositories\Interfaces\SubServiceItemVariantRepositoryInterface;

class SubServiceItemVariantManagerService
{
    protected SubServiceItemVariantRepositoryInterface $subServiceItemVariantRepository;

    public function __construct(SubServiceItemVariantRepositoryInterface $subServiceItemVariantRepository)
    {
        $this->subServiceItemVariantRepository = $subServiceItemVariantRepository;
    }

    public function getAllSubServiceItemVariants()
    {
        return $this->subServiceItemVariantRepository->all();
    }

    public function getSubServiceItemVariantById($id)
    {
        return $this->subServiceItemVariantRepository->find($id);
    }

    public function createSubServiceItemVariant(array $data)
    {
        return $this->subServiceItemVariantRepository->create($data);
    }

    public function updateSubServiceItemVariant($id, array $data)
    {
        return $this->subServiceItemVariantRepository->update($id, $data);
    }

    public function deleteSubServiceItemVariant($id)
    {
        return $this->subServiceItemVariantRepository->delete($id);
    }
}

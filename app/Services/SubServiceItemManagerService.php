<?php

namespace App\Services;

use App\Models\SubServiceItem;
use App\Repositories\Interfaces\SubServiceItemRepositoryInterface;

class SubServiceItemManagerService
{
    protected SubServiceItemRepositoryInterface $subServiceItemRepository;

    public function __construct(SubServiceItemRepositoryInterface $subServiceItemRepository)
    {
        $this->subServiceItemRepository = $subServiceItemRepository;
    }

    public function getAllSubServiceItems()
    {
        return $this->subServiceItemRepository->all();
    }

    public function getSubServiceItemById($id)
    {
        return $this->subServiceItemRepository->find($id);
    }

    public function createSubServiceItem(array $data)
    {
        return $this->subServiceItemRepository->create($data);
    }

    public function updateSubServiceItem($id, array $data): SubServiceItem
    {
        return $this->subServiceItemRepository->update($id, $data);
    }

    public function deleteSubServiceItem($id)
    {
        return $this->subServiceItemRepository->delete($id);
    }
}

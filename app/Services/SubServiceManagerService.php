<?php

namespace App\Services;

use App\Models\SubService;
use App\Repositories\Interfaces\SubServiceItemRepositoryInterface;
use App\Repositories\Interfaces\SubServiceRepositoryInterface;
use Illuminate\Support\Facades\DB;

class SubServiceManagerService
{
    protected SubServiceRepositoryInterface $subServiceRepository;
    protected SubServiceItemRepositoryInterface $subServiceItemRepository;

    public function __construct(
        SubServiceRepositoryInterface $subServiceRepository,
        SubServiceItemRepositoryInterface $subServiceItemRepository
    )
    {
        $this->subServiceRepository = $subServiceRepository;
        $this->subServiceItemRepository = $subServiceItemRepository;
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

    public function createSubServiceWithItems(array $subServiceData, array | null $itemsData)
    {
        return DB::transaction(function () use ($subServiceData, $itemsData) {
            $subService = $this->subServiceRepository->create($subServiceData);
            if($subService->type === 'Variant Based'){
                $this->subServiceItemRepository->createManyForSubService($subService, $itemsData);
            }
            return $subService;
        });
    }

    public function updateSubServiceWithItems(SubService $subService, array $subServiceData, ?array $itemsData)
    {
        return DB::transaction(function () use ($subService, $subServiceData, $itemsData) {

            $this->subServiceRepository->update($subService, $subServiceData);

            if ($subService->type === 'Variant Based') {
                $this->subServiceItemRepository->syncForSubService($subService, $itemsData ?? []);
            }
            return $subService;
        });
    }

    public function getByServiceId(int $serviceId)
    {
        return $this->subServiceRepository->findByService($serviceId);
    }
}

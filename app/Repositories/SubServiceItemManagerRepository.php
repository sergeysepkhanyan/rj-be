<?php

namespace App\Repositories;

use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Repositories\Interfaces\SubServiceItemRepositoryInterface;
use App\Repositories\Interfaces\SubServiceItemVariantRepositoryInterface;
use Illuminate\Support\Collection;

class SubServiceItemManagerRepository implements SubServiceItemRepositoryInterface
{
    protected SubServiceItemVariantRepositoryInterface $variantRepository;

    public function __construct(SubServiceItemVariantRepositoryInterface $variantRepository)
    {
        $this->variantRepository = $variantRepository;
    }

    public function all()
    {
        return SubServiceItem::all();
    }

    public function find($id)
    {
        return SubServiceItem::findOrFail($id);
    }

    public function create(array $data)
    {
        return SubServiceItem::create($data);
    }

    public function delete($id)
    {
        $subServiceItem = SubServiceItem::findOrFail($id);
        return $subServiceItem->delete();
    }

    public function createManyForSubService(SubService $subService, array $items): Collection
    {
        $createdItems = new Collection();

        foreach ($items as $itemData) {
            $itemOnlyData = collect($itemData)->except('variants')->toArray();

            /** @var SubServiceItem $subServiceItem */
            $subServiceItem = $subService->items()->create($itemOnlyData);

            if ($itemData['type'] === 'Variant Based') {
                $this->variantRepository->createManyForSubServiceItem($subServiceItem, $itemData['variants']);
            }

            $createdItems->push($subServiceItem);
        }

        return $createdItems;
    }

    public function syncForSubService(SubService $subService, array $items): Collection
    {
        $existingItems = $subService->items()->pluck('id')->toArray();
        $requestedItems = collect($items)->pluck('id')->filter()->toArray();
        $itemsToDelete = array_diff($existingItems, $requestedItems);
        if (!empty($itemsToDelete)) {
            $subService->items()->whereIn('id', $itemsToDelete)->delete();
        }
        $syncedItems = new Collection();
        foreach ($items as $itemData) {
            $itemOnlyData = collect($itemData)->except('variants')->toArray();
            if (array_key_exists('id', $itemData) && $itemData['id'] !== null) {
                $item = $subService->items()->find($itemData['id']);
                $item->update($itemOnlyData);
            } else {
                $item = $subService->items()->create($itemOnlyData);
            }
            if ($itemData['type'] === 'Variant Based') {
                $this->variantRepository->syncForSubServiceItem($item, $itemData['variants'] ?? []);
            } elseif ($itemData['type'] === 'Simple') {
                $item->variants()->delete();
            }

            $syncedItems->push($item);
        }

        return $syncedItems;
    }

    public function update(SubServiceItem $item, array $data): SubServiceItem
    {
        $item->update($data);
        return $item;
    }
}

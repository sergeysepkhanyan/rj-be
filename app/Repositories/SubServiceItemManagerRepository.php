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
            $itemOnlyData = collect($itemData)->toArray();

            /** @var SubServiceItem $subServiceItem */
            $subServiceItem = $subService->items()->create($itemOnlyData);

            $createdItems->push($subServiceItem);
        }

        return $createdItems;
    }

    public function syncForSubService(SubService $subService, array $items): Collection
    {
        $syncedItems = new Collection();

        foreach ($items as $itemData) {

            if (!empty($itemData['id'])) {
                $item = $subService->items()->where('id', $itemData['id'])->update($itemData);

            } else {
                $item = $subService->items()->create($itemData);
            }

            if (isset($item)) {
                $syncedItems->push($item);
            }
        }

        return $syncedItems;
    }


    public function update(SubServiceItem $item, array $data): SubServiceItem
    {
        $item->update($data);
        return $item;
    }
}

<?php

namespace App\Repositories;

use App\Models\SubServiceItem;
use App\Models\SubServiceItemVariant;
use App\Repositories\Interfaces\SubServiceItemVariantRepositoryInterface;
use Illuminate\Support\Collection;

class SubServiceItemVariantManagerRepository implements SubServiceItemVariantRepositoryInterface
{
    public function all()
    {
        return SubServiceItemVariant::all();
    }

    public function find($id)
    {
        return SubServiceItemVariant::findOrFail($id);
    }

    public function create(array $data)
    {
        return SubServiceItemVariant::create($data);
    }

    public function delete($id)
    {
        $subServiceItemVariant = SubServiceItemVariant::findOrFail($id);
        return $subServiceItemVariant->delete();
    }

    public function createManyForSubServiceItem(SubServiceItem $subServiceItem, array $variants): Collection
    {
        return $subServiceItem->variants()->createMany($variants);
    }

    public function syncForSubServiceItem(SubServiceItem $subServiceItem, array $variants): Collection
    {
        $existingVariants = $subServiceItem->variants()->pluck('id')->toArray();
        $requestedVariants = collect($variants)->pluck('id')->filter()->toArray();
        $variantsToDelete = array_diff($existingVariants, $requestedVariants);
        if (!empty($variantsToDelete)) {
            $subServiceItem->variants()->whereIn('id', $variantsToDelete)->delete();
        }
        $syncedVariants = new Collection();
        foreach ($variants as $variantData) {
            if (array_key_exists('id', $variantData) && $variantData['id'] !== null) {
                $variant = $subServiceItem->variants()->find($variantData['id']);
                $variant->update($variantData);
            } else {
                $variant = $subServiceItem->variants()->create($variantData);
            }
            $syncedVariants->push($variant);
        }

        return $syncedVariants;
    }

    public function update(SubServiceItemVariant $variant, array $data): SubServiceItemVariant
    {
        $variant->update($data);
        return $variant;
    }
}

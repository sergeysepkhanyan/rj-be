<?php

namespace App\Repositories\Interfaces;

use App\Models\SubServiceItem;
use App\Models\SubServiceItemVariant;
use Illuminate\Support\Collection;

interface SubServiceItemVariantRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function delete($id);
    public function createManyForSubServiceItem(SubServiceItem $subServiceItem, array $variants): Collection;
    public function syncForSubServiceItem(SubServiceItem $subServiceItem, array $variants): Collection;
    public function update(SubServiceItemVariant $variant, array $data): SubServiceItemVariant;

}

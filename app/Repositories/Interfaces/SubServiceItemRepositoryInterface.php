<?php

namespace App\Repositories\Interfaces;

use App\Models\SubService;
use App\Models\SubServiceItem;
use Illuminate\Support\Collection;

interface SubServiceItemRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function delete($id);
    public function syncForSubService(SubService $subService, array $items): Collection;
    public function update(SubServiceItem $item, array $data): SubServiceItem;
}

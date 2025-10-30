<?php

namespace App\Repositories;

use App\Models\SubServiceItem;
use App\Repositories\Interfaces\SubServiceItemRepositoryInterface;

class SubServiceItemManagerRepository implements SubServiceItemRepositoryInterface
{
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

    public function update($id, array $data)
    {
        $subServiceItem = SubServiceItem::findOrFail($id);
        $subServiceItem->update($data);
        return $subServiceItem;
    }

    public function delete($id)
    {
        $subServiceItem = SubServiceItem::findOrFail($id);
        return $subServiceItem->delete();
    }
}

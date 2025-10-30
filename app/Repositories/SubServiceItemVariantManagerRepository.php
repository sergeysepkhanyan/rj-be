<?php

namespace App\Repositories;

use App\Models\SubServiceItemVariant;
use App\Repositories\Interfaces\SubServiceItemVariantRepositoryInterface;

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

    public function update($id, array $data)
    {
        $subServiceItemVariant = SubServiceItemVariant::findOrFail($id);
        $subServiceItemVariant->update($data);
        return $subServiceItemVariant;
    }

    public function delete($id)
    {
        $subServiceItemVariant = SubServiceItemVariant::findOrFail($id);
        return $subServiceItemVariant->delete();
    }
}

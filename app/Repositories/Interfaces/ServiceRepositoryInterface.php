<?php

namespace App\Repositories\Interfaces;

use App\Models\Service;

interface ServiceRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(Service $service, array $data): Service;
    public function delete(Service $service);
}

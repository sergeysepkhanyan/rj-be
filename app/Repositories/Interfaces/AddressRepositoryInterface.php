<?php

namespace App\Repositories\Interfaces;

use App\Models\Address;
interface AddressRepositoryInterface
{
    public function all();
    public function find($id);
    public function create(array $data);
    public function update(Address $address, array $data): Address;
    public function delete($id);
}

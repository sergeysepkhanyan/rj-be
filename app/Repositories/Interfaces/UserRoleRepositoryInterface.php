<?php

namespace App\Repositories\Interfaces;

interface UserRoleRepositoryInterface
{
    public function all();
    public function find($id);
}

<?php

namespace App\Repositories;

use App\Models\UserRole;
use App\Repositories\Interfaces\UserRoleRepositoryInterface;

class UserRoleRepository implements UserRoleRepositoryInterface
{
    public function findBySlug(string $slug): ?UserRole
    {
        return UserRole::where('slug', $slug)->first();
    }

    public function find($id): ?UserRole
    {
        return UserRole::find($id);
    }

    public function all()
    {
        return UserRole::all();
    }
}


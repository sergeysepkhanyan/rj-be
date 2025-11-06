<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;

class UserRepository implements UserRepositoryInterface
{
    public function all()
    {
        return User::all();
    }

    public function find($id)
    {
        return User::findOrFail($id);
    }

    public function create(array $data)
    {
        return User::create($data);
    }

    public function update($id, array $data)
    {
        $user = User::findOrFail($id);
        $user->update($data);
        return $user;
    }

    public function delete($id)
    {
        $user = User::findOrFail($id);
        return $user->delete();
    }

    public function countAdmins(): int
    {
        return User::whereHas('role', fn($q) => $q->where('name', 'admin'))->count();
    }

    public function allStaff()
    {
        return User::whereHas('role', function($q) {
            $q->whereNotIn('slug', ['superadmin', 'client']);
        })->get();
    }

    public function paginateStaff(int $perPage = 10, int $page = 1)
    {
        return User::whereHas('role', function ($q) {
            $q->whereNotIn('slug', ['superadmin', 'client']);
        })
            ->with(['role', 'subservices.items.variants'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

}


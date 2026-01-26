<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

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

    public function update(User $user, array $data): User
    {
        $user->update($data);
        return $user;
    }

    public function delete(User $user): ?bool
    {
        return $user->delete();
    }

    public function countAdmins(): int
    {
        return User::whereHas('role', fn($q) => $q->where('slug', 'admin'))->count();
    }

    public function countMarketers(): int
    {
        return User::whereHas('role', fn($q) => $q->where('slug', 'marketer'))->count();
    }

    public function allStaff()
    {
        return User::whereHas('role', function($q) {
            $q->whereNotIn('slug', ['superadmin', 'client']);
        })->get();
    }

    public function paginateStaff(int $perPage = 10, int $page = 1): LengthAwarePaginator
    {
        return User::withTrashed()
        ->whereHas('role', function ($q) {
            $q->whereNotIn('slug', ['superadmin', 'client']);
        })
            ->with(['role', 'subservices.items'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function allMasters()
    {
        return User::whereHas('role', function($q) {
            $q->where('slug', 'master');
        })
            ->with(['role', 'subservices.items', 'masterBookings'])
            ->get();
    }

    public function paginateClients(int $perPage = 10, int $page = 1)
    {
        return User::whereHas('role', function ($q) {
            $q->where('slug', 'client');
        })
            ->with(['role'])
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    public function getMastersForSubservice(int $subserviceId)
    {
        return User::query()
            ->masters()
            ->whereHas('subServices', function ($q) use ($subserviceId) {
                $q->where('sub_services.id', $subserviceId);
            })
            ->get();
    }

    public function restore(int $id): User
    {
        /** @var User $user */
        $user = User::withTrashed()->findOrFail($id);
        $user->restore();
        $user->load(['role', 'subservices.items']);

        return $user;
    }

}


<?php

namespace App\Services;

use App\Mail\AdminAccessEmail;
use App\Repositories\Interfaces\UserRepositoryInterface;
use App\Repositories\Interfaces\UserRoleRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UserService
{
    protected UserRepositoryInterface $userRepository;
    protected UserRoleRepositoryInterface $userRoleRepository;

    public function __construct(
        UserRepositoryInterface $userRepository,
        UserRoleRepositoryInterface $userRoleRepository
    )
    {
        $this->userRepository = $userRepository;
        $this->userRoleRepository = $userRoleRepository;
    }

    public function getAllUsers()
    {
        return $this->userRepository->all();
    }

    public function getUserById($id)
    {
        return $this->userRepository->find($id);
    }

    public function createUser(array $data)
    {
        $subservices = $data['subservices'] ?? [];
        $roleName = $data['role'] ?? null;
        $role = $this->userRoleRepository->findBySlug($roleName);
        $data = array_diff_key($data, array_flip(['role', 'subservices']));
        $data['user_role_id'] = $role->id;
        $generatedPassword = Str::random(6);
        if ($role->slug === 'admin') {
            $data['password'] = Hash::make($generatedPassword);
            $data['is_temporary_password'] = true;
        }
        $user = $this->userRepository->create($data);
        if ($role->slug === 'master' && !empty($subservices)) {
            $user->subservices()->sync($subservices);
        }
        if ($role->slug === 'admin') {
            Mail::to($user->email)->send(new AdminAccessEmail($user, $generatedPassword));
        }
        return $user->load('role', 'subservices.items.variants');
    }

    public function createStaffMembers(array $usersData)
    {
        return DB::transaction(function () use ($usersData) {
            $created = [];

            foreach ($usersData as $userData) {
                $created[] = $this->createUser($userData);
            }

            return $created;
        });
    }

    public function updateStaff(array $usersData)
    {
        return DB::transaction(function () use ($usersData) {

            $currentMembers = $this->userRepository->allStaff();
            $incomingUserIds = [];
            $updatedOrCreatedUsers = collect();

            foreach ($usersData as $userData) {
                $user = null;

                if (!empty($userData['id'])) {
                    $user = $this->userRepository->find($userData['id']);
                    if (!$user) continue;
                    $incomingUserIds[] = $user->id;
                    $updatedOrCreatedUsers->push(
                        $this->updateStaffMember($user->id, $userData)
                    );
                } else {
                    $newUser = $this->createUser($userData);
                    $incomingUserIds[] = $newUser->id;
                    $updatedOrCreatedUsers->push($newUser);
                }
            }

            $usersToDelete = $currentMembers->whereNotIn('id', $incomingUserIds);
            foreach ($usersToDelete as $user) {
                if (!in_array($user->role->slug, ['superadmin', 'client'])) {
                    $this->deleteUser($user->id);
                }
            }

            return $updatedOrCreatedUsers->values();
        });
    }


    public function updateStaffMember(int $id, array $data)
    {
        return DB::transaction(function () use ($id, $data) {
            $user = $this->userRepository->find($id);
            $subservices = $data['subservices'] ?? [];
            $roleName = $data['role'] ?? null;
            $role = $this->userRoleRepository->findBySlug($roleName);

            $fields = array_diff_key($data, array_flip(['role', 'subservices']));
            $fields['user_role_id'] = $role->id;

            $oldRole = $user->role->slug;

            if ($oldRole !== 'admin' && $role->slug === 'admin') {
                $generatedPassword = Str::random(6);
                $fields['password'] = Hash::make($generatedPassword);
                $fields['is_temporary_password'] = true;

                $accessLink = config('app.url') . '/admin/login';
                Mail::to($user->email)->send(new AdminAccessEmail($user, $generatedPassword, $accessLink));
            }

            $this->updateUser($user->id, $fields);

            if ($role->slug === 'master') {
                $user->subservices()->sync($subservices);
            }
            return $this->userRepository->find($user->id)->load('role', 'subservices.items.variants');
        });
    }


    public function updateUser($id, array $data)
    {
        return $this->userRepository->update($id, $data);
    }

    public function deleteUser($id)
    {
        return $this->userRepository->delete($id);
    }

    public function changePassword($userId, array $data): array
    {
        $user = $this->userRepository->find($userId);

        if (!Hash::check($data['old_password'], $user->password)) {
            return [
                'success' => false,
                'message' => 'Old password is incorrect',
            ];
        }

        $updated = $this->userRepository->update($userId, [
            'password' => bcrypt($data['password']),
        ]);

        if (!$updated) {
            return [
                'success' => false,
                'message' => 'Failed to update password',
            ];
        }

        return [
            'success' => true,
            'message' => 'Password changed successfully',
        ];
    }

    public function canAddAdmins(int $newAdminsCount): bool
    {
        $existingAdminsCount = $this->userRepository->countAdmins();

        return ($existingAdminsCount + $newAdminsCount) <= 2;
    }

    public function getPaginatedStaff(int $perPage = 10, int $page = 1)
    {
        return $this->userRepository->paginateStaff($perPage, $page);
    }
}


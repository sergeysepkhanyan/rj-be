<?php

namespace App\Services;

use App\Repositories\Interfaces\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;

class UserService
{
    protected UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
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
        return $this->userRepository->create($data);
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
}


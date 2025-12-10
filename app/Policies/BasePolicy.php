<?php

namespace App\Policies;

use App\Models\User;

abstract class BasePolicy
{

    public function before(User $user, $ability): ?true
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }
    public function update(User $user, $model): bool
    {
        return $model->isOwnedBy($user);
    }

    public function delete(User $user, $model): bool
    {
        return $model->isOwnedBy($user);
    }
}

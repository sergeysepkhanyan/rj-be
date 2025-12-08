<?php

namespace App\Policies;

use App\Models\User;

abstract class BasePolicy
{
    public function update(User $user, $model): bool
    {
        return $model->isOwnedBy($user);
    }

    public function delete(User $user, $model): bool
    {
        return $model->isOwnedBy($user);
    }
}

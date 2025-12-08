<?php

namespace App\Traits;

use App\Models\User;

/**
 * @property int $user_id
 */
trait BelongsToUser
{
    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}

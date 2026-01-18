<?php

namespace App\Repositories\Interfaces;

use App\Models\CartItem;
use Illuminate\Support\Collection;

interface CartItemRepositoryInterface
{
    public function create(array $data): CartItem;
    public function update(CartItem $item, array $data): CartItem;
    public function delete(CartItem $item): ?bool;
    public function findBySessionProduct(?int $userId, ?string $guestSessionId, int $productId): ?CartItem;
    public function listBySession(?int $userId, ?string $guestSessionId): Collection;
    public function deleteBySession(?int $userId, ?string $guestSessionId): int;
    public function assignGuestSessionToUser(string $guestSessionId, int $userId): int;
}

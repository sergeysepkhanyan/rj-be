<?php

namespace App\Repositories;

use App\Models\CartItem;
use App\Repositories\Interfaces\CartItemRepositoryInterface;
use Illuminate\Support\Collection;

class CartItemRepository implements CartItemRepositoryInterface
{
    public function create(array $data): CartItem
    {
        return CartItem::create($data);
    }

    public function update(CartItem $item, array $data): CartItem
    {
        $item->update($data);
        return $item;
    }

    public function delete(CartItem $item): ?bool
    {
        return $item->delete();
    }

    public function findBySessionProduct(?int $userId, ?string $guestSessionId, int $productId): ?CartItem
    {
        return CartItem::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId && $guestSessionId, fn ($q) => $q->where('guest_session_id', $guestSessionId))
            ->where('product_id', $productId)
            ->first();
    }

    public function listBySession(?int $userId, ?string $guestSessionId): Collection
    {
        return CartItem::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId && $guestSessionId, fn ($q) => $q->where('guest_session_id', $guestSessionId))
            ->with('product')
            ->orderByDesc('created_at')
            ->get();
    }

    public function deleteBySession(?int $userId, ?string $guestSessionId): int
    {
        return CartItem::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId && $guestSessionId, fn ($q) => $q->where('guest_session_id', $guestSessionId))
            ->delete();
    }

    public function assignGuestSessionToUser(string $guestSessionId, int $userId): int
    {
        return CartItem::query()
            ->where('guest_session_id', $guestSessionId)
            ->update([
                'user_id' => $userId,
                'guest_session_id' => null,
            ]);
    }
}

<?php

namespace App\Repositories;

use App\Models\BookingSelection;
use App\Repositories\Interfaces\BookingSelectionRepositoryInterface;
use Illuminate\Support\Collection;

class BookingSelectionRepository implements BookingSelectionRepositoryInterface
{
    public function create(array $data): BookingSelection
    {
        return BookingSelection::create($data);
    }

    public function update(BookingSelection $selection, array $data): BookingSelection
    {
        $selection->update($data);
        return $selection;
    }

    public function delete(BookingSelection $selection): ?bool
    {
        return $selection->delete();
    }

    public function getByGuestSession(string $guestSessionId): Collection
    {
        return BookingSelection::query()
            ->where('guest_session_id', $guestSessionId)
            ->get();
    }

    public function assignGuestSessionToUser(string $guestSessionId, int $userId): int
    {
        return BookingSelection::query()
            ->where('guest_session_id', $guestSessionId)
            ->update([
                'user_id' => $userId,
                'guest_session_id' => null,
            ]);
    }

    public function deleteByUserId(int $userId): int
    {
        return BookingSelection::query()
            ->where('user_id', $userId)
            ->delete();
    }

    public function deleteByGuestSession(string $guestSessionId): int
    {
        return BookingSelection::query()
            ->where('guest_session_id', $guestSessionId)
            ->delete();
    }

    public function hasOverlapForSession(
        ?int $userId,
        ?string $guestSessionId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeId = null
    ): bool {
        if (!$userId && !$guestSessionId) {
            return false;
        }

        return BookingSelection::query()
            ->whereDate('date', $date)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->when(!$userId && $guestSessionId, fn ($q) => $q->where('guest_session_id', $guestSessionId))
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($startTime, $endTime) {
                $q->where('start_time', '<', $endTime)
                    ->where('end_time', '>', $startTime);
            })
            ->exists();
    }
}

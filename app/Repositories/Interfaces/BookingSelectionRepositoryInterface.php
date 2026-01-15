<?php

namespace App\Repositories\Interfaces;

use App\Models\BookingSelection;
use Illuminate\Support\Collection;

interface BookingSelectionRepositoryInterface
{
    public function create(array $data): BookingSelection;
    public function update(BookingSelection $selection, array $data): BookingSelection;
    public function delete(BookingSelection $selection): ?bool;
    public function getByGuestSession(string $guestSessionId): Collection;
    public function assignGuestSessionToUser(string $guestSessionId, int $userId): int;
    public function deleteByUserId(int $userId): int;
    public function deleteByGuestSession(string $guestSessionId): int;
    public function hasOverlapForSession(
        ?int $userId,
        ?string $guestSessionId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeId = null
    ): bool;
}

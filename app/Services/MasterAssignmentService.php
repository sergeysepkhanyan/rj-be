<?php

namespace App\Services;

use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Repositories\Interfaces\BookingRepositoryInterface;
use Illuminate\Http\Exceptions\HttpResponseException;

class MasterAssignmentService
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
    ) {}

    public function assignAndValidateMasters(
        string $date,
        string $tz,
        array $services,
        ?int $excludeBookingId = null
    ): array {
        $services = collect($services)->values()->all();

        foreach ($services as $i => $s) {
            $anyMaster = (bool)($s['any_master'] ?? false);

            $serviceType = $s['service_type'] ?? null;
            $serviceId   = (int)($s['service_id'] ?? 0);

            $startTime = (string)($s['start_time'] ?? '');
            $endTime   = (string)($s['end_time'] ?? '');
            $providedMasterId = $s['master_id'] ?? null;

            if (!$serviceType || !$serviceId || !$startTime || !$endTime) {
                throw new HttpResponseException(
                    ApiResponse::error(['services' => "services[$i] missing required fields."], 'Validation failed', 422)
                );
            }

            if ($anyMaster) {
                $chosenMasterId = $this->pickAvailableMasterForService(
                    serviceType: $serviceType,
                    serviceId: $serviceId,
                    date: $date,
                    startTime: $startTime,
                    endTime: $endTime,
                    excludeBookingId: $excludeBookingId
                );

                $services[$i]['master_id'] = $chosenMasterId;
                $services[$i]['any_master'] = true;

                continue;
            }

            if (empty($providedMasterId)) {
                throw new HttpResponseException(
                    ApiResponse::error(['services' => "services[$i].master_id is required when any_master is false."], 'Validation failed', 422)
                );
            }

            $masterId = (int) $providedMasterId;

            if (!$this->isMasterEligibleForService($masterId, $serviceType, $serviceId)) {
                throw new HttpResponseException(
                    ApiResponse::error(['services' => "services[$i] selected master cannot perform this service."], 'Validation failed', 422)
                );
            }

            $this->assertMasterFree(
                masterId: $masterId,
                date: $date,
                startTime: $startTime,
                endTime: $endTime,
                excludeBookingId: $excludeBookingId
            );
        }

        return $services;
    }

    private function pickAvailableMasterForService(
        string $serviceType,
        int $serviceId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId
    ): int {
        $candidateMasterIds = $this->getEligibleMasterIdsForService($serviceType, $serviceId);

        if (empty($candidateMasterIds)) {
            throw new HttpResponseException(
                ApiResponse::error(['master' => 'No masters can perform this service.'], 'Validation failed', 422)
            );
        }

        foreach ($candidateMasterIds as $mid) {
            $hasOverlap = $this->bookingRepository->hasOverlap(
                masterId: (int) $mid,
                date: $date,
                startTime: substr((string) $startTime, 0, 5),
                endTime: substr((string) $endTime, 0, 5),
                excludeBookingId: $excludeBookingId
            );

            if (! $hasOverlap) {
                return (int) $mid;
            }
        }

        throw new HttpResponseException(
            ApiResponse::error(['master' => 'No available master for selected time range.'], 'Validation failed', 422)
        );
    }

    private function assertMasterFree(
        int $masterId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId
    ): void {
        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId: $masterId,
            date: $date,
            startTime: substr((string) $startTime, 0, 5),
            endTime: substr((string) $endTime, 0, 5),
            excludeBookingId: $excludeBookingId
        );

        if ($hasOverlap) {
            throw new HttpResponseException(
                ApiResponse::error(['masterId' => 'Master is not available in selected time range.'], 'Validation failed', 422)
            );
        }
    }

    private function getEligibleMasterIdsForService(string $serviceType, int $serviceId): array
    {
        if ($serviceType === 'subservice') {
            $sub = SubService::query()
                ->with('masters:id')
                ->findOrFail($serviceId);

            return $sub->masters->pluck('id')->all();
        }

        if ($serviceType === 'item') {
            $item = SubServiceItem::query()
                ->with('subService.masters:id')
                ->findOrFail($serviceId);
            return $item->subService?->masters?->pluck('id')->all() ?? [];
        }

        throw new HttpResponseException(
            ApiResponse::error(['serviceType' => 'Unknown service type.'], 'Validation failed', 422)
        );
    }


    private function isMasterEligibleForService(int $masterId, string $serviceType, int $serviceId): bool
    {
        $eligible = $this->getEligibleMasterIdsForService($serviceType, $serviceId);
        return in_array($masterId, $eligible, true);
    }
}

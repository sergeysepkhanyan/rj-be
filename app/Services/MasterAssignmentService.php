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
            $anyMaster = (bool)($s['anyMaster'] ?? $s['any_master'] ?? false);

            $serviceType = $s['serviceType'] ?? $s['service_type'] ?? null;
            $serviceId   = (int)($s['serviceId'] ?? $s['service_id'] ?? 0);

            $startTime = (string)($s['startTime'] ?? $s['start_time'] ?? '');
            $endTime   = (string)($s['endTime'] ?? $s['end_time'] ?? '');

            $providedMasterId = $s['masterId'] ?? $s['master_id'] ?? null;

            if (!$serviceType || !$serviceId || !$startTime || !$endTime) {
                $this->throwValidation([
                    "services.$i.serviceId"   => __('validation.booking.service_type_and_id_required'),
                    "services.$i.startTime"   => __('validation.booking.service_start_required'),
                    "services.$i.endTime"     => __('validation.booking.service_end_required'),
                ], 'validation.failed');
            }

            if ($anyMaster) {
                $chosenMasterId = $this->pickAvailableMasterForService(
                    serviceType: (string) $serviceType,
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
                $this->throwValidation([
                    "services.$i.masterId" => __('validation.booking.master_required_when_any_false'),
                ], 'validation.failed');
            }

            $masterId = (int) $providedMasterId;

            if (!$this->isMasterEligibleForService($masterId, (string) $serviceType, $serviceId)) {
                $this->throwValidation([
                    "services.$i.masterId" => __('validation.booking.master_cannot_perform_service'),
                ], 'validation.failed');
            }

            $this->assertMasterFree(
                masterId: $masterId,
                date: $date,
                startTime: $startTime,
                endTime: $endTime,
                excludeBookingId: $excludeBookingId,
                errorKey: "services.$i.masterId"
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
            $this->throwValidation([
                'masterId' => __('validation.booking.no_masters_for_service'),
            ], 'validation.failed');
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

        $this->throwValidation([
            'masterId' => __('validation.booking.no_available_master'),
        ], 'validation.failed');
    }

    private function assertMasterFree(
        int $masterId,
        string $date,
        string $startTime,
        string $endTime,
        ?int $excludeBookingId,
        string $errorKey = 'masterId'
    ): void {
        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId: $masterId,
            date: $date,
            startTime: substr((string) $startTime, 0, 5),
            endTime: substr((string) $endTime, 0, 5),
            excludeBookingId: $excludeBookingId
        );

        if ($hasOverlap) {
            $this->throwValidation([
                $errorKey => __('validation.booking.master_unavailable'),
            ], 'validation.failed');
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

        $this->throwValidation([
            'serviceType' => __('validation.booking.unknown_service_type'),
        ], 'validation.failed');
    }

    private function isMasterEligibleForService(int $masterId, string $serviceType, int $serviceId): bool
    {
        $eligible = $this->getEligibleMasterIdsForService($serviceType, $serviceId);
        return in_array($masterId, $eligible, true);
    }

    protected function throwValidation(array $errors, string $messageKey, array $replace = [], int $status = 422): never
    {

        $normalized = array_map(function ($v) {
            return is_array($v) ? array_values($v) : [(string)$v];
        }, $errors);

        throw new HttpResponseException(
            \App\Services\ApiResponse::error($normalized, __($messageKey, $replace), $status)
        );
    }
}


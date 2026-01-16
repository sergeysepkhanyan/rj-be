<?php

namespace App\Services;

use App\Models\SubService;
use App\Models\SubServiceItem;
use App\Models\User;
use Carbon\CarbonImmutable;
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
            $serviceTypeRaw = $s['serviceType'] ?? $s['service_type'] ?? null;
            $serviceType = strtolower(trim((string)$serviceTypeRaw));

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
                    serviceType: $serviceType,
                    serviceId: $serviceId,
                    date: $date,
                    tz: $tz,
                    startTime: $startTime,
                    endTime: $endTime,
                    excludeBookingId: $excludeBookingId
                );

                $services[$i]['master_id'] = $chosenMasterId;
                $services[$i]['any_master'] = true;
                $services[$i]['service_type'] = $serviceType;

                continue;
            }

            if (empty($providedMasterId)) {
                $this->throwValidation([
                    "services.$i.masterId" => __('validation.booking.master_required_when_any_false'),
                ], 'validation.failed');
            }

            $masterId = (int) $providedMasterId;

            if (!$this->isMasterEligibleForService($masterId, $serviceType, $serviceId)) {
                $this->throwValidation([
                    "services.$i.masterId" => __('validation.booking.master_cannot_perform_service'),
                ], 'validation.failed');
            }

            $this->assertMasterNotOff(
                masterId: $masterId,
                date: $date,
                tz: $tz,
                errorKey: "services.$i.masterId"
            );

            $this->assertMasterFree(
                masterId: $masterId,
                date: $date,
                startTime: $startTime,
                endTime: $endTime,
                timezone: $tz,
                excludeBookingId: $excludeBookingId,
                errorKey: "services.$i.masterId"
            );

            $services[$i]['service_type'] = $serviceType;
            $services[$i]['master_id'] = $masterId;
        }

        return $services;
    }

    private function pickAvailableMasterForService(
        string $serviceType,
        int $serviceId,
        string $date,
        string $tz,
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
            if ($this->isMasterOffOnDate((int)$mid, $date, $tz)) {
                continue;
            }

            $hasOverlap = $this->bookingRepository->hasOverlap(
                masterId: (int) $mid,
                date: $date,
                startTime: substr((string) $startTime, 0, 5),
                endTime: substr((string) $endTime, 0, 5),
                excludeBookingId: $excludeBookingId,
                timezone: $tz
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
        string $timezone,
        ?int $excludeBookingId,
        string $errorKey = 'masterId'
    ): void {
        $hasOverlap = $this->bookingRepository->hasOverlap(
            masterId: $masterId,
            date: $date,
            startTime: substr((string) $startTime, 0, 5),
            endTime: substr((string) $endTime, 0, 5),
            excludeBookingId: $excludeBookingId,
            timezone: $timezone
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

    private function isMasterOffOnDate(int $masterId, string $date, string $tz): bool
    {
        $day = CarbonImmutable::createFromFormat('Y-m-d', $date, $tz);
        $isoDay = $day->dayOfWeekIso;

        return User::query()
            ->whereKey($masterId)
            ->masters()
            ->whereHas('weekends', fn ($q) => $q->where('day', $isoDay))
            ->exists();

    }

    private function assertMasterNotOff(int $masterId, string $date, string $tz, string $errorKey = 'masterId'): void
    {
        if ($this->isMasterOffOnDate($masterId, $date, $tz)) {
            $this->throwValidation([
                $errorKey => __('validation.booking.master_day_off'),
            ], 'validation.failed');
        }
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


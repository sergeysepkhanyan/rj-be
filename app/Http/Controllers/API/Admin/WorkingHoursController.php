<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\ApiResponse;
use App\Services\WorkingHourService;
use Illuminate\Http\JsonResponse;

class WorkingHoursController extends Controller
{
    public function __construct(private WorkingHourService $service) {}

    public function bulkUpdate(WorkingHoursBulkUpdateRequest $request): JsonResponse
    {
        $this->service->bulkUpdate($request->validated()['days']);

        return ApiResponse::success(['success' => true], 'Working hours updated.');
    }

    public function updateDay(int $day, WorkingHoursUpdateDayRequest $request): JsonResponse
    {
        $this->service->updateDay($day, $request->validated());

        return ApiResponse::success(['success' => true], 'Working hours updated.');
    }
}

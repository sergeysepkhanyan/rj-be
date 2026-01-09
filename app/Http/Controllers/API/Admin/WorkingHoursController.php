<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkingHoursBulkUpdateRequest;
use App\Http\Requests\WorkingHoursUpdateDayRequest;
use App\Services\ApiResponse;
use App\Services\WorkingHourService;
use Illuminate\Http\JsonResponse;

class WorkingHoursController extends Controller
{
    public function __construct(private readonly WorkingHourService $service) {}

    public function bulkUpdate(WorkingHoursBulkUpdateRequest $request): JsonResponse
    {
        $this->service->bulkUpdate($request->only('days')['days']);

        return ApiResponse::success(['success' => true], 'Working hours updated.');
    }

    public function updateDay(int $day, WorkingHoursUpdateDayRequest $request): JsonResponse
    {
        $this->service->updateDay($day, $request->all());

        return ApiResponse::success(['success' => true], 'Working hours updated.');
    }
}

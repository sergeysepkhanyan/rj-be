<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkingHourResource;
use App\Services\ApiResponse;
use App\Services\WorkingHourService;
use Illuminate\Http\JsonResponse;

class WorkingHoursController extends Controller
{
    public function __construct(private readonly WorkingHourService $service) {}

    public function index(): JsonResponse
    {
        return ApiResponse::success([
            'workingHours' => WorkingHourResource::collection($this->service->getSchedule()),
        ]);
    }
}

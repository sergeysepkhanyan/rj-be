<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\WeekdayResource;
use App\Services\ApiResponse;
use App\Services\WeekdayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;


class WeekdaysController
{
    protected WeekdayService $weekdayService;

    public function __construct(WeekdayService $weekdayService)
    {
        $this->weekdayService = $weekdayService;
    }

    public function index(Request $request): JsonResponse
    {
        $services = $this->weekdayService->getAllDays();

        return ApiResponse::success([
            'weekdays' => WeekdayResource::collection($services)
        ]);
    }
}

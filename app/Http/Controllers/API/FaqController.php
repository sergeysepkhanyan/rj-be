<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Services\ApiResponse;
use App\Services\FaqService;
use Illuminate\Http\JsonResponse;

class FaqController extends Controller
{
    public function __construct(private readonly FaqService $service) {}

    public function index(): JsonResponse
    {
        $faqs = $this->service->listActive();

        return ApiResponse::success([
            'faqs' => FaqResource::collection($faqs),
        ]);
    }
}

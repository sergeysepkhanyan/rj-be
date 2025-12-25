<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContactRequest;
use App\Services\ApiResponse;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;

class ContactController extends Controller
{
    public function __construct(private readonly ContactService $service) {}

    public function store(StoreContactRequest $request): JsonResponse
    {
        $this->service->submit(
            payload: $request->validated(),
            ip: $request->ip(),
            userAgent: $request->userAgent()
        );

        return ApiResponse::success(['success' => true], 'Message sent successfully');

    }
}

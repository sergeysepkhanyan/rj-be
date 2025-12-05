<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Services\ApiResponse;
use App\Services\PageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagesController extends Controller
{
    protected PageService $pageService;

    public function __construct(PageService $pageService)
    {
        $this->pageService = $pageService;
    }

    public function index(): JsonResponse
    {
        return ApiResponse::success(PageResource::collection($this->pageService->getAllPages()));
    }
}

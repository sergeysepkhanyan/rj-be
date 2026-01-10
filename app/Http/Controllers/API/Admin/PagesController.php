<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PageResource;
use App\Services\ApiResponse;
use App\Services\PageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagesController extends Controller
{
    public function __construct(protected PageService $pageService) {}

    public function update(Request $request): JsonResponse
    {
        $data = $request->all();

        $page = $this->pageService->update($data);

        return ApiResponse::success(
            new PageResource($page),
            __('success.page.updated')
        );
    }
}

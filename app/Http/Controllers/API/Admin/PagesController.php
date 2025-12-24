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
    protected PageService $pageService;

    public function __construct(PageService $pageService)
    {
        $this->pageService = $pageService;
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->all();
        $page = $this->pageService->update($data);
        return ApiResponse::success(new PageResource($page), 'Page updated successfully.');
    }
}

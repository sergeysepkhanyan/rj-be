<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePagesRequest;
use App\Http\Resources\PageResource;
use App\Services\ApiResponse;
use App\Services\PageService;
use Illuminate\Http\JsonResponse;

class PagesController extends Controller
{
    public function __construct(protected PageService $pageService) {}

    public function update(UpdatePagesRequest $request): JsonResponse
    {
        $page = $this->pageService->update($request->all());

        return ApiResponse::success(
            new PageResource($page),
            __('success.page.updated')
        );
    }
}

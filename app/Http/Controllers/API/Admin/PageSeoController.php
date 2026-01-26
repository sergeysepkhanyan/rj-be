<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePageSeoRequest;
use App\Http\Resources\PageSeoResource;
use App\Services\ApiResponse;
use App\Services\PageSeoService;
use Illuminate\Http\JsonResponse;

class PageSeoController extends Controller
{
    public function __construct(
        protected PageSeoService $pageSeoService
    ) {}

    public function index(): JsonResponse
    {
        $pages = $this->pageSeoService->getAll();

        return ApiResponse::success($pages);
    }

    public function show(string $pageKey): JsonResponse
    {
        $pageSeo = $this->pageSeoService->getByKey($pageKey);

        if (!$pageSeo) {
            return ApiResponse::error(null, __('messages.resource_not_found'), 404);
        }

        return ApiResponse::success(
            (new PageSeoResource($pageSeo))->resolve()
        );
    }

    public function update(UpdatePageSeoRequest $request, string $pageKey): JsonResponse
    {
        $data = $request->all();

        $pageSeo = $this->pageSeoService->updateByKey($pageKey, $data);

        return ApiResponse::success(
            (new PageSeoResource($pageSeo))->resolve(),
            __('success.page_seo.updated')
        );
    }
}

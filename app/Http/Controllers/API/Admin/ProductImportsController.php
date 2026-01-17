<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportProductsRequest;
use App\Services\ApiResponse;
use App\Services\ProductImportService;
use Illuminate\Http\JsonResponse;

class ProductImportsController extends Controller
{
    public function __construct(
        protected ProductImportService $productImportService
    ) {}

    public function import(ImportProductsRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $dryRun = (bool) ($request->input('dry_run') ?? $request->input('dryRun') ?? false);

        $result = $this->productImportService->import($file, $dryRun);

        return ApiResponse::success([
            'created' => $result['created'],
            'failed' => $result['failed'],
            'errors' => $result['errors'],
        ], __('success.product.imported'));
    }
}

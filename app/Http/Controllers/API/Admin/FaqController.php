<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaqResource;
use App\Models\Faq;
use App\Services\ApiResponse;
use App\Services\FaqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends Controller
{
    public function __construct(private readonly FaqService $service) {}

    public function index(): JsonResponse
    {
        $faqs = $this->service->list();

        return ApiResponse::success([
            'faqs' => FaqResource::collection($faqs),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category' => 'nullable|string|max:255',
            'category_ar' => 'nullable|string|max:255',
            'question' => 'required|string',
            'question_ar' => 'nullable|string',
            'answer' => 'required|string',
            'answer_ar' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $faq = $this->service->create($validated);

        return ApiResponse::success([
            'faq' => new FaqResource($faq),
        ], __('success.faq.created'), 201);
    }

    public function show(int $id): JsonResponse
    {
        $faq = $this->service->find($id);

        if (!$faq) {
            return ApiResponse::error(__('error.faq.not_found'), 404);
        }

        return ApiResponse::success([
            'faq' => new FaqResource($faq),
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $faq = $this->service->find($id);

        if (!$faq) {
            return ApiResponse::error(__('error.faq.not_found'), 404);
        }

        $validated = $request->validate([
            'category' => 'nullable|string|max:255',
            'category_ar' => 'nullable|string|max:255',
            'question' => 'sometimes|required|string',
            'question_ar' => 'nullable|string',
            'answer' => 'sometimes|required|string',
            'answer_ar' => 'nullable|string',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $faq = $this->service->update($faq, $validated);

        return ApiResponse::success([
            'faq' => new FaqResource($faq),
        ], __('success.faq.updated'));
    }

    public function destroy(int $id): JsonResponse
    {
        $faq = $this->service->find($id);

        if (!$faq) {
            return ApiResponse::error(__('error.faq.not_found'), 404);
        }

        $this->service->delete($faq);

        return ApiResponse::success([
            'success' => true,
        ], __('success.faq.deleted'));
    }

    public function reorder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:faqs,id',
        ]);

        $this->service->reorder($validated['ids']);

        return ApiResponse::success([
            'success' => true,
        ], __('success.faq.reordered'));
    }
}

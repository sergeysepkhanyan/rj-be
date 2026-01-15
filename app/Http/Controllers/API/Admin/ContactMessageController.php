<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactMessageResource;
use App\Services\ApiResponse;
use App\Services\ContactService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\ContactMessage;

class ContactMessageController extends Controller
{
    public function __construct(private readonly ContactService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'from', 'to', 'unread']);
        $perPage = (int) $request->query('per_page', 15);

        $items = $this->service->list($filters, $perPage);

        return ApiResponse::success([
            'items' => ContactMessageResource::collection($items),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'links' => [
                'first' => $items->url(1),
                'last' => $items->url($items->lastPage()),
                'prev' => $items->previousPageUrl(),
                'next' => $items->nextPageUrl(),
            ]
        ]);
    }

    public function markRead(ContactMessage $contactMessage): JsonResponse
    {
        $message = $this->service->markRead($contactMessage);

        return ApiResponse::success([
            'item' => new ContactMessageResource($message),
        ]);
    }

    public function unreadCount(): JsonResponse
    {
        return ApiResponse::success([
            'count' => $this->service->countUnread(),
        ]);
    }
}

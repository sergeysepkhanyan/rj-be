<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddReferralRequest;
use App\Http\Resources\ClientResource;
use App\Models\User;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientsController extends Controller
{
    public function __construct(protected UserService $userService) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->get('per_page', 10);
        $page    = (int) $request->get('page', 1);

        $clients = $this->userService->getPaginatedClients($perPage, $page);

        return ApiResponse::success([
            'users' => ClientResource::collection($clients),
            'meta' => [
                'current_page' => $clients->currentPage(),
                'last_page' => $clients->lastPage(),
                'per_page' => $clients->perPage(),
                'total' => $clients->total(),
            ],
            'links' => [
                'first' => $clients->url(1),
                'last' => $clients->url($clients->lastPage()),
                'prev' => $clients->previousPageUrl(),
                'next' => $clients->nextPageUrl(),
            ],
        ], __('success.client.list'));
    }

    public function addReferral(AddReferralRequest $request, User $user): JsonResponse
    {
        $client = $this->userService->updateUser($user, $request->all());

        return ApiResponse::success([
            'user' => new ClientResource($client),
        ], __('success.client.referral_added'));
    }
}

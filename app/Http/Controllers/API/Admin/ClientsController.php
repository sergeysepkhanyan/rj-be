<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStaffRequest;
use App\Http\Requests\UpdateStaffRequest;
use App\Http\Resources\ClientResource;
use App\Http\Resources\StaffResource;
use App\Models\User;
use App\Services\ApiResponse;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ClientsController extends Controller
{
    protected UserService $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 10);
            $page = $request->get('page', 1);

            $staff = $this->userService->getPaginatedClients($perPage, $page);

            return ApiResponse::success([
                'users' => StaffResource::collection($staff),
                'meta' => [
                    'current_page' => $staff->currentPage(),
                    'last_page' => $staff->lastPage(),
                    'per_page' => $staff->perPage(),
                    'total' => $staff->total(),
                ],
                'links' => [
                    'first' => $staff->url(1),
                    'last' => $staff->url($staff->lastPage()),
                    'prev' => $staff->previousPageUrl(),
                    'next' => $staff->nextPageUrl(),
                ],
            ], 'Clients retrieved successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }

    public function addReferral(Request $request, User $user): JsonResponse
    {

        try {

            $data = $request->all();

            $validator = Validator::make($data, [
                'referral_id' => 'nullable|exists:referrals,id',
            ]);

            if ($validator->fails()) {
                return ApiResponse::error($validator->errors(), 'Validation failed', 422);
            }

            $client = $this->userService->updateUser($user->id, $data);

            return ApiResponse::success([
                'user' => new ClientResource($client),
            ], 'Referral added successfully');
        } catch (\Exception $e) {
            return ApiResponse::error();
        }
    }
}

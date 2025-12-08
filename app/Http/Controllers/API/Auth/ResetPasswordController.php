<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\PasswordService;
use App\Services\ApiResponse;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    protected PasswordService $passwordService;

    public function __construct(PasswordService $passwordService)
    {
        $this->passwordService = $passwordService;
    }

    public function forgot(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'identifier' => 'required|string',
            ]);

            $response = $this->passwordService->sendResetLink($request->identifier);
            if(!$response['success']){
                return ApiResponse::error(['token' => [$response['message'] ?? 'Reset link failed']], 'Reset link failed', 400);
            }

            return ApiResponse::success($response, 'Reset link sent successfully');

        } catch (ValidationException $e) {
            return ApiResponse::error($e->errors(), 'Validation failed', 422);

        } catch (\Throwable $e) {
            return ApiResponse::error();
        }
    }

    public function reset(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'identifier' => 'required|string',
                'token' => 'required|string',
                'password' => 'required|string|confirmed|min:6',
            ]);

            $response = $this->passwordService->resetPassword(
                $request->identifier,
                $request->token,
                $request->password
            );

            if (!$response['success']) {
                return ApiResponse::error(
                    ['token' => [$response['message'] ?? 'Password reset failed']],
                    'Password reset failed',
                    400
                );
            }

            return ApiResponse::success(
                $response,
                'Password reset successfully'
            );

        } catch (ValidationException $e) {
            return ApiResponse::error($e->errors(), 'Validation failed', 422);

        } catch (\Throwable $e) {
            return ApiResponse::error();
        }
    }
}


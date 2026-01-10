<?php

namespace App\Http\Controllers\API\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordService;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ResetPasswordController extends Controller
{
    public function __construct(protected PasswordService $passwordService) {}

    public function forgot(Request $request): JsonResponse
    {
        $request->validate([
            'identifier' => ['required', 'string'],
        ], [
            'identifier.required' => __('validation.custom.identifier.required'),
            'identifier.string'   => __('validation.custom.identifier.string'),
        ], [
            'identifier' => __('attributes.identifier'),
        ]);

        $response = $this->passwordService->sendResetLink($request->identifier);

        if (!($response['success'] ?? false)) {
            $serviceMsg = $response['message'] ?? null;

            return ApiResponse::error(
                ['identifier' => [$serviceMsg ?: __('errors.password.reset_link_failed')]],
                __('errors.password.reset_link_failed'),
                400
            );
        }

        return ApiResponse::success(
            $response,
            __('success.password.reset_link_sent')
        );
    }

    public function reset(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'identifier' => ['required', 'string'],
                'token'      => ['required', 'string'],
                'password'   => ['required', 'string', 'confirmed', 'min:6'],
            ], [
                'identifier.required' => __('validation.custom.identifier.required'),
                'identifier.string'   => __('validation.custom.identifier.string'),

                'token.required'      => __('validation.custom.token.required'),
                'token.string'        => __('validation.custom.token.string'),

                'password.required'   => __('validation.custom.password.required'),
                'password.string'     => __('validation.custom.password.string'),
                'password.confirmed'  => __('validation.custom.password.confirmed'),
                'password.min'        => __('validation.custom.password.min'),
            ], [
                'identifier' => __('attributes.identifier'),
                'token'      => __('attributes.token'),
                'password'   => __('attributes.password'),
            ]);

            $response = $this->passwordService->resetPassword(
                $request->identifier,
                $request->token,
                $request->password
            );

            if (!($response['success'] ?? false)) {
                $serviceMsg = $response['message'] ?? null;

                return ApiResponse::error(
                    ['token' => [$serviceMsg ?: __('errors.password.reset_failed')]],
                    __('errors.password.reset_failed'),
                    400
                );
            }

            return ApiResponse::success(
                $response,
                __('success.password.reset_done')
            );

        } catch (ValidationException $e) {
            return ApiResponse::error($e->errors(), __('validation.failed'), 422);

        } catch (\Throwable $e) {
            return ApiResponse::error(null, __('errors.common.server_error'), 500);
        }
    }
}



<?php

namespace App\Http\Controllers\API;
use App\Models\User;
use App\Services\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController
{


    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return ApiResponse::error(['link' => ['Invalid verification link']], 'Invalid link', 403);
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $user->forceFill(['is_active' => true])->save();
            event(new Verified($user));
        }

        return ApiResponse::success(['verified' => true], 'Email verified successfully.');
    }

}

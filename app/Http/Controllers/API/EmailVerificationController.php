<?php

namespace App\Http\Controllers\API;

use App\Mail\VerifyEmailMail;
use App\Models\User;
use App\Services\ApiResponse;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class EmailVerificationController
{
    public function verify(Request $request, int $id, string $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        if (! hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return ApiResponse::error(
                ['link' => [__('validation.auth.invalid_verification_link')]],
                __('validation.auth.invalid_verification_link'),
                403
            );
        }

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $user->forceFill(['status' => 'active'])->save();
            event(new Verified($user));
        }

        return ApiResponse::success(
            ['verified' => true],
            __('success.auth.email_verified')
        );
    }

    public function resend(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'exists:users,email'],
        ], [
            'email.required' => __('validation.custom.email.required'),
            'email.email' => __('validation.custom.email.email'),
            'email.exists' => __('validation.custom.email.exists'),
        ]);

        $user = User::where('email', $request->email)->firstOrFail();

        if ($user->hasVerifiedEmail()) {
            return ApiResponse::error(
                ['email' => [__('validation.auth.email_already_verified')]],
                __('validation.auth.email_already_verified'),
                422
            );
        }

        $redirectTo = $request->input('redirect_to');
        Mail::to($user->email)->queue(new VerifyEmailMail($user, $redirectTo));

        return ApiResponse::success(
            ['sent' => true],
            __('success.auth.verification_email_resent')
        );
    }
}


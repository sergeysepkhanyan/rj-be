<?php

namespace App\Services;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class PasswordService
{
    public function sendResetLink(string $identifier): array
    {
        $user = User::where('email', $identifier)
            ->orWhere('mobile', $identifier)
            ->first();

        if (!$user) {
            return [
                'success' => false,
                'message_key' => 'errors.password.user_not_found',
                'message' => __('errors.password.user_not_found')
            ];
        }

        $token = Str::random(60);
        DB::table('password_resets')->updateOrInsert(
            ['identifier' => $identifier],
            ['token' => $token, 'created_at' => now()]
        );

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            Mail::to($user->email)->queue(new ResetPasswordMail($user, $token));
        }

        return [
            'success' => true,
            'message_key' => 'success.password.reset_link_sent',
            'message' => __('success.password.reset_link_sent')
        ];
    }

    public function resetPassword(string $identifier, string $token, string $newPassword): array
    {
        $expirationMinutes = 60;
        $expirationTime = now()->subMinutes($expirationMinutes);

        $record = DB::table('password_resets')
            ->where('identifier', $identifier)
            ->where('token', $token)
            ->where('created_at', '>=', $expirationTime)
            ->first();

        if (!$record) {
            return [
                'success' => false,
                'message_key' => 'errors.password.invalid_token',
                'message' => __('errors.password.invalid_token')
            ];
        }

        $user = User::where('email', $identifier)
            ->orWhere('mobile', $identifier)
            ->firstOrFail();

        $updateData = [
            'password' => Hash::make($newPassword),
        ];

        if (!$user->hasVerifiedEmail()) {
            $updateData['email_verified_at'] = now();
        }

        $user->update($updateData);

        DB::table('password_resets')->where('identifier', $identifier)->delete();

        return [
            'success' => true,
            'message_key' => 'success.password.reset_done',
            'message' => __('success.password.reset_done')
        ];
    }
}


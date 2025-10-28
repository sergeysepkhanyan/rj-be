<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
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
                'message' => 'User not found.'
            ];
        }

        $token = Str::random(60);
        DB::table('password_resets')->updateOrInsert(
            ['identifier' => $identifier],
            ['token' => $token, 'created_at' => now()]
        );

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user->sendPasswordResetNotification($token);
        } else {
            // Placeholder for SMS sending
            // SmsService::send($identifier, "Your reset code is $token");
        }

        return [
            'success' => true,
            'message' => 'Password reset link sent.'
        ];
    }

    public function resetPassword(string $identifier, string $token, string $newPassword): array
    {
        $record = DB::table('password_resets')
            ->where('identifier', $identifier)
            ->where('token', $token)
            ->first();

        if (!$record) {
            return [
                'success' => false,
                'message' => 'Invalid token.'
            ];
        }

        $user = User::where('email', $identifier)
            ->orWhere('mobile', $identifier)
            ->firstOrFail();

        $user->password = Hash::make($newPassword);
        $user->save();

        DB::table('password_resets')->where('identifier', $identifier)->delete();

        return [
            'success' => true,
            'message' => 'Password has been reset successfully.'
        ];
    }
}


<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApiResponse;
use Illuminate\Http\JsonResponse;

class MarketingPreferenceController extends Controller
{
    /**
     * One-click unsubscribe from the marketing stream. Transactional and
     * loyalty emails are unaffected — those are never gated on consent.
     */
    public function unsubscribe(string $token): JsonResponse
    {
        $user = User::where('unsubscribe_token', $token)->first();

        if (! $user) {
            return ApiResponse::error(
                ['token' => ['Invalid or expired unsubscribe link.']],
                'Invalid or expired unsubscribe link.',
                404
            );
        }

        if ($user->marketing_opt_in) {
            $user->forceFill([
                'marketing_opt_in' => false,
                'marketing_opt_in_at' => null,
            ])->save();
        }

        return ApiResponse::success([
            'email' => $user->email,
            'marketingOptIn' => false,
        ], 'You have been unsubscribed from marketing emails.');
    }
}

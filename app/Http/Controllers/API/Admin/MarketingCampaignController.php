<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Mail\MarketingCampaignMail;
use App\Models\User;
use App\Services\ApiResponse;
use App\Services\MarketingDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/**
 * Stream 3 (marketing) campaigns. Every send is routed through
 * MarketingDispatcher so only opted-in clients receive it, and each email
 * carries that recipient's unsubscribe link.
 */
class MarketingCampaignController extends Controller
{
    public function __construct(protected MarketingDispatcher $dispatcher) {}

    /**
     * Size of the reachable marketing audience (opted-in customers with an email).
     */
    public function audience(): JsonResponse
    {
        $count = User::customers()
            ->where('marketing_opt_in', true)
            ->whereNotNull('email')
            ->count();

        return ApiResponse::success(['audienceCount' => $count]);
    }

    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:20000'],
            'testEmail' => ['nullable', 'email'],
        ]);

        // Test send: straight to the given address with a placeholder unsubscribe link.
        if (!empty($validated['testEmail'])) {
            Mail::to($validated['testEmail'])->queue(new MarketingCampaignMail(
                '[TEST] ' . $validated['subject'],
                $validated['body'],
                rtrim((string) config('app.frontend_url'), '/') . '/unsubscribe/test',
            ));

            return ApiResponse::success(['sent' => 1, 'skipped' => 0], 'Test email queued');
        }

        $sent = 0;
        $skipped = 0;

        User::customers()
            ->where('marketing_opt_in', true)
            ->whereNotNull('email')
            ->chunkById(200, function ($customers) use ($validated, &$sent, &$skipped) {
                foreach ($customers as $customer) {
                    $ok = $this->dispatcher->send($customer, new MarketingCampaignMail(
                        $validated['subject'],
                        $validated['body'],
                        $this->dispatcher->unsubscribeUrl($customer),
                        $customer->name ?: trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? '')),
                    ));
                    $ok ? $sent++ : $skipped++;
                }
            });

        return ApiResponse::success([
            'sent' => $sent,
            'skipped' => $skipped,
        ], "Campaign queued to {$sent} opted-in clients");
    }
}

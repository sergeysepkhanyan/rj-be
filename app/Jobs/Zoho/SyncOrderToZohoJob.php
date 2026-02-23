<?php

namespace App\Jobs\Zoho;

use App\Models\Order;
use App\Services\ZohoSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncOrderToZohoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        public readonly Order $order,
    ) {}

    public function handle(ZohoSyncService $zohoSyncService): void
    {
        $zohoSyncService->syncOrder($this->order);
    }
}

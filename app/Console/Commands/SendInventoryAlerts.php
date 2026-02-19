<?php

namespace App\Console\Commands;

use App\Services\InventoryAlertService;
use Illuminate\Console\Command;

class SendInventoryAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:send-alerts';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check inventory for low stock, expiring, and expired products and send alerts to admin';

    /**
     * Execute the console command.
     */
    public function handle(InventoryAlertService $alertService): int
    {
        $this->info('Checking inventory alerts...');

        $result = $alertService->checkAndSendAlerts();

        $this->info("Low stock products: {$result['counts']['lowStock']}");
        $this->info("Expiring soon products: {$result['counts']['expiringSoon']}");
        $this->info("Expired products: {$result['counts']['expired']}");

        if ($result['sent']) {
            $this->info($result['message']);
        } else {
            $this->warn($result['message']);
        }

        return Command::SUCCESS;
    }
}

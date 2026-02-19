<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InventoryAlertMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $lowStockProducts,
        public array $expiringSoonProducts,
        public array $expiredProducts
    ) {}

    public function build(): self
    {
        $totalAlerts = count($this->lowStockProducts) + count($this->expiringSoonProducts) + count($this->expiredProducts);

        $subject = "Inventory Alert: {$totalAlerts} product(s) need attention";

        return $this->subject($subject)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.inventory-alert')
            ->text('emails.inventory-alert-text')
            ->with([
                'lowStockProducts' => $this->lowStockProducts,
                'expiringSoonProducts' => $this->expiringSoonProducts,
                'expiredProducts' => $this->expiredProducts,
                'totalAlerts' => $totalAlerts,
            ]);
    }
}

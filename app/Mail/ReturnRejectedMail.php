<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReturnRejectedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public OrderReturn $orderReturn,
    ) {}

    public function build(): self
    {
        $reference = $this->order->reference ?? ('#' . $this->order->id);

        $customerName = null;
        if ($this->order->user) {
            $customerName = trim(($this->order->user->name ?? '') . ' ' . ($this->order->user->last_name ?? ''));
        }
        $customerName = $customerName ?: (($this->order->meta ?? [])['customer_name'] ?? 'there');

        return $this->subject('Return Request Update - Order ' . $reference)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.return-rejected')
            ->with([
                'reference' => $reference,
                'customerName' => $customerName,
                'adminNotes' => $this->orderReturn->admin_notes,
                'orderAmount' => $this->order->amount,
                'currency' => $this->order->currency ?? 'AED',
            ]);
    }
}

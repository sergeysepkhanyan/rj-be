<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderReturn;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReturnRequestedMail extends Mailable implements ShouldQueue
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
        $customerName = $customerName ?: (($this->order->meta ?? [])['customer_name'] ?? 'Guest');

        return $this->subject('Return Request - Order ' . $reference)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.return-requested')
            ->with([
                'reference' => $reference,
                'customerName' => $customerName,
                'reason' => $this->orderReturn->reason,
                'orderAmount' => $this->order->amount,
                'currency' => $this->order->currency ?? 'AED',
                'createdAt' => $this->orderReturn->created_at?->format('d M Y, h:i A'),
            ]);
    }
}

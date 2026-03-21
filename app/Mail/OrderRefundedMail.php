<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderRefundedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
    ) {}

    public function build(): self
    {
        $reference = $this->order->reference ?? ('ORD-' . $this->order->id);

        return $this->subject('Your order ' . $reference . ' has been refunded')
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.order-refunded')
            ->with([
                'reference' => $reference,
                'amount' => number_format((float) $this->order->amount, 2),
                'currency' => $this->order->currency ?? 'AED',
            ]);
    }
}

<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class OrderConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(
        public Order $order,
        public string $recipientEmail,
    ) {}

    public function build(): OrderConfirmedMail
    {
        try {
            Log::info('[order][email][build] Starting email build', [
                'order_id' => $this->order->id,
                'recipient_email' => $this->recipientEmail,
            ]);

            // Use simple template for now (we know it works)
            // TODO: Debug full template separately
            $order = $this->order->load(['items.product']);
            
            Log::info('[order][email][build] Order loaded', [
                'order_id' => $order->id,
                'items_count' => $order->items->count(),
            ]);

            $orderData = [
                'id' => $order->id,
                'reference' => $order->reference ?? 'ORD-' . $order->id,
                'amount' => (string) $order->amount,
                'currency' => $order->currency ?? 'AED',
                'createdAt' => $order->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
            ];

            $subject = 'Order Confirmed #' . ($orderData['reference'] ?? $order->id);

            Log::info('[order][email][build] Building email with simple template', [
                'order_id' => $order->id,
                'subject' => $subject,
                'recipient' => $this->recipientEmail,
            ]);

            $mail = $this->to($this->recipientEmail)
                ->subject($subject)
                ->from(config('mail.from.address'), config('mail.from.name'))
                ->view('emails.order-confirmed-simple')
                ->text('emails.order-confirmed-simple-text')
                ->with(['order' => $orderData]);

            Log::info('[order][email][build] Email built successfully', [
                'order_id' => $order->id,
                'recipient' => $this->recipientEmail,
            ]);

            return $mail;
        } catch (\Throwable $e) {
            Log::error('[order][email][build] OrderConfirmedMail build failed completely', [
                'order_id' => $this->order->id,
                'recipient' => $this->recipientEmail,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}

<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewOrderAdminNotificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(
        public Order $order
    ) {}

    public function build(): self
    {
        $order = $this->order->load([
            'items.product',
            'shippingAddress',
            'user',
        ]);

        $customerName = $order->user?->name ?? ($order->meta['customer_name'] ?? 'Guest');
        $customerEmail = $order->user?->email ?? ($order->meta['customer_email'] ?? 'N/A');
        $customerPhone = $order->user?->mobile ?? ($order->meta['customer_phone'] ?? 'N/A');

        $orderData = [
            'id' => $order->id,
            'reference' => $order->reference,
            'type' => $order->type,
            'amount' => (float) $order->amount,
            'currency' => $order->currency ?? 'AED',
            'status' => $order->status,
            'createdAt' => $order->created_at?->format('d M Y, H:i'),
            'customer' => [
                'name' => $customerName,
                'email' => $customerEmail,
                'phone' => $customerPhone,
            ],
            'items' => $order->items->map(function ($item) {
                return [
                    'productName' => $item->product?->name ?? 'N/A',
                    'quantity' => (int) $item->quantity,
                    'unitPrice' => (float) $item->unit_price,
                    'subtotal' => (float) $item->subtotal,
                ];
            })->all(),
            'shippingAddress' => $order->shippingAddress ? [
                'name' => trim(($order->shippingAddress->name ?? '') . ' ' . ($order->shippingAddress->last_name ?? '')),
                'address' => $order->shippingAddress->address,
                'city' => $order->shippingAddress->city,
                'country' => $order->shippingAddress->country?->name ?? null,
                'zipCode' => $order->shippingAddress->zip_code,
            ] : null,
        ];

        $orderData = $this->stripMissingValues($orderData);

        $reference = $order->reference ?? ('#' . $order->id);
        $subject = 'New Order Received #' . $reference . ' - ' . number_format($order->amount, 2) . ' ' . ($order->currency ?? 'AED');

        return $this->subject($subject)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.new-order-admin')
            ->text('emails.new-order-admin-text')
            ->with(['order' => $orderData]);
    }
}

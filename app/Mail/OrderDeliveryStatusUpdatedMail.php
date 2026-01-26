<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderDeliveryStatusUpdatedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(
        public Order $order,
        public string $deliveryStatus
    ) {}

    public function build(): OrderDeliveryStatusUpdatedMail
    {
        $order = $this->order->load([
            'items.product',
            'shippingAddress',
            'user',
        ]);

        $customerEmail = $order->user?->email ?? ($order->meta['customer_email'] ?? null);
        if (!$customerEmail) {
            return $this;
        }

        $orderData = [
            'id' => $order->id,
            'reference' => $order->reference,
            'deliveryStatus' => $this->deliveryStatus,
            'deliveryStatusLabel' => $this->getDeliveryStatusLabel($this->deliveryStatus),
            'items' => $order->items->map(function ($item) {
                return [
                    'productName' => $item->product?->name ?? 'N/A',
                    'quantity' => (int) $item->quantity,
                ];
            })->all(),
            'shippingAddress' => $order->shippingAddress ? [
                'name' => $order->shippingAddress->name,
                'lastName' => $order->shippingAddress->last_name,
                'address' => $order->shippingAddress->address,
                'city' => $order->shippingAddress->city,
                'state' => $order->shippingAddress->state,
                'zipCode' => $order->shippingAddress->zip_code,
            ] : null,
        ];

        $orderData = $this->stripMissingValues($orderData);

        $subject = match($this->deliveryStatus) {
            'delivered' => 'Order Delivered #' . ($orderData['reference'] ?? $order->reference ?? $order->id),
            'out_of_delivery' => 'Your Order is Out for Delivery #' . ($orderData['reference'] ?? $order->reference ?? $order->id),
            'canceled' => 'Order Canceled #' . ($orderData['reference'] ?? $order->reference ?? $order->id),
            default => 'Order Status Updated #' . ($orderData['reference'] ?? $order->reference ?? $order->id),
        };

        return $this->subject($subject)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.order-delivery-status-updated')
            ->text('emails.order-delivery-status-updated-text')
            ->with(['order' => $orderData]);
    }

    protected function getDeliveryStatusLabel(string $status): string
    {
        return match($status) {
            'ordered' => 'Ordered',
            'out_of_delivery' => 'Out of Delivery',
            'delivered' => 'Delivered',
            'canceled' => 'Canceled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}

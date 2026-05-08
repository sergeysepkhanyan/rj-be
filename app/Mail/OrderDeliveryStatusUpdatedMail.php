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
            'shippingAddress.country',
            'user',
        ]);

        // If the queued status string is empty for any reason, fall back to
        // whatever is currently on the order. Otherwise the email renders
        // an empty "Current Status" box.
        $resolvedStatus = $this->deliveryStatus !== ''
            ? $this->deliveryStatus
            : (string) ($order->delivery_status ?? '');

        $rows = \App\Models\OrderItem::with('product')
            ->where('order_id', $order->id)
            ->get();

        $orderData = [
            'id' => $order->id,
            'reference' => $order->reference,
            'deliveryStatus' => $resolvedStatus,
            'deliveryStatusLabel' => $this->getDeliveryStatusLabel($resolvedStatus),
            'items' => $rows->map(function ($item) {
                $name = $item->product?->name;
                return [
                    'productName' => $name !== null && $name !== '' ? $name : 'Product',
                    'quantity' => (int) $item->quantity,
                ];
            })->all(),
            'shippingAddress' => $order->shippingAddress ? [
                'name' => $order->shippingAddress->name,
                'lastName' => $order->shippingAddress->last_name,
                'address' => $order->shippingAddress->address,
                'city' => $order->shippingAddress->city,
                'country' => $order->shippingAddress->country?->name ?? ($order->shippingAddress->country_id ? 'Country #' . $order->shippingAddress->country_id : null),
                'zipCode' => $order->shippingAddress->zip_code,
            ] : null,
        ];

        $orderData = $this->stripMissingValues($orderData);

        $subject = match($resolvedStatus) {
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
            'out_of_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'canceled' => 'Canceled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * Mailable auto-passes public properties to the view, which would
     * overwrite the `order` key we set via `->with()` with the raw Order
     * model. The blade template expects the resolved payload array
     * (deliveryStatusLabel, mapped items), so we suppress the auto-pass.
     */
    public function buildViewData(): array
    {
        return $this->viewData;
    }
}

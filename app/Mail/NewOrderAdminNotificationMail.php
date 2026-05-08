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
            'shippingAddress.country',
            'user',
        ]);

        // Re-query items directly from DB; relying on the rehydrated
        // $order->items relation has been flaky in production.
        $rows = \App\Models\OrderItem::with('product')
            ->where('order_id', $order->id)
            ->get();

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
            'items' => $rows->map(function ($item) {
                $name = $item->product?->name;
                $unit = (float) $item->unit_price;
                $sub = (float) $item->subtotal;
                $qty = max(1, (int) $item->quantity);
                if ($unit <= 0 && $sub > 0) {
                    $unit = round($sub / $qty, 2);
                }
                return [
                    'productName' => $name !== null && $name !== '' ? $name : 'Product',
                    'quantity' => $qty,
                    'unitPrice' => $unit,
                    'subtotal' => $sub,
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

    /**
     * Mailable auto-passes public properties to the view, which would
     * overwrite the `order` key we set via `->with()` with the raw Order
     * model. The blade template expects the resolved payload array, so
     * we suppress the auto-pass.
     */
    public function buildViewData(): array
    {
        return $this->viewData;
    }
}

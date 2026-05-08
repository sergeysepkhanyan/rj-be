<?php

namespace App\Mail;

use App\Models\Order;
use App\Http\Resources\OrderResource;
use App\Support\StripsMissingValues;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OrderConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels, StripsMissingValues;

    public function __construct(
        public Order $order,
        public string $recipientEmail,
    ) {}

    public function build(): self
    {
        $order = $this->order->load([
            'items.product.files',
            'shippingAddress.country',
            'billingAddress.country',
            'latestPayment',
            'user',
        ]);

        $payload = (new OrderResource($order))->resolve();
        $payload = $this->stripMissingValues($payload);

        $typeValue = method_exists($order, 'getTypeValue') ? $order->getTypeValue() : (string) $order->type;
        if ($typeValue === 'ecommerce') {
            $rows = \App\Models\OrderItem::with('product')
                ->where('order_id', $order->id)
                ->get();

            $items = $rows->map(function ($item) {
                $product = $item->product;
                $name = $product?->name ?: 'Product';
                $unit = (float) $item->unit_price;
                $sub = (float) $item->subtotal;
                $qty = max(1, (int) $item->quantity);
                if ($unit <= 0 && $sub > 0) {
                    $unit = round($sub / $qty, 2);
                }
                $mainImage = $product?->main_image
                    ? asset('storage/' . $product->main_image)
                    : null;

                return [
                    'id' => $item->id,
                    'productId' => $item->product_id,
                    'name' => $name,
                    'productName' => $name,
                    'skuId' => $product?->sku_id,
                    'image' => $mainImage,
                    'quantity' => $qty,
                    'unitPrice' => $unit,
                    'subtotal' => $sub,
                ];
            })->all();
        } else {
            $items = $payload['items'] ?? [];
            $items = is_array($items) ? $items : (method_exists($items, 'all') ? $items->all() : []);
            if (!empty($items)) {
                $items = array_map(function ($item) {
                    $row = is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : []);
                    if (isset($row['name']) && !isset($row['productName'])) {
                        $row['productName'] = $row['name'];
                    }
                    return $row;
                }, $items);
            }
        }
        $payload['items'] = $items;

        if (isset($payload['createdAt']) && $payload['createdAt'] instanceof \Carbon\Carbon) {
            $payload['createdAt'] = $payload['createdAt']->format('Y-m-d H:i:s');
        }
        if (isset($payload['paidAt']) && $payload['paidAt'] instanceof \Carbon\Carbon) {
            $payload['paidAt'] = $payload['paidAt']->format('Y-m-d H:i:s');
        }

        $paymentMethod = null;
        if ($order->latestPayment) {
            $paymentRaw = $order->latestPayment->raw ?? [];
            $charges = data_get($paymentRaw, 'charges.data', []);
            if (!empty($charges) && is_array($charges)) {
                $firstCharge = $charges[0] ?? [];
                $pmDetails = data_get($firstCharge, 'payment_method_details.card', []);
                if (!empty($pmDetails)) {
                    $paymentMethod = [
                        'provider' => $order->latestPayment->provider ?? 'card',
                        'brand' => data_get($pmDetails, 'brand'),
                        'last4' => data_get($pmDetails, 'last4'),
                    ];
                }
            }
            if (!$paymentMethod) {
                $paymentMethod = [
                    'provider' => $order->latestPayment->provider ?? 'card',
                ];
            }
        }
        $payload['paymentMethod'] = $paymentMethod;

        $reference = $payload['reference'] ?? ('#' . ($payload['id'] ?? $order->id));
        $subject = 'Order Confirmed #' . $reference;

        return $this->to($this->recipientEmail)
            ->subject($subject)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->view('emails.order-confirmed')
            ->text('emails.order-confirmed-text')
            ->with(['order' => $payload]);
    }

    /**
     * Mailable auto-passes public properties to the view, which would
     * overwrite the `order` key we explicitly pass via `->with()` with the
     * raw Eloquent model. The blade template expects the resolved payload
     * array (with productName, createdAt, etc.), so we suppress the
     * auto-pass and only expose what we explicitly set.
     */
    public function buildViewData(): array
    {
        return $this->viewData;
    }
}

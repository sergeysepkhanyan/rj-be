@php
    $items = $order['items'] ?? [];
    $items = is_array($items) ? $items : (method_exists($items, 'all') ? $items->all() : []);
    $shippingAddress = $order['shippingAddress'] ?? null;
    $billingAddress = $order['billingAddress'] ?? null;
    $paymentMethod = $order['paymentMethod'] ?? null;

    $fmt = fn($n) => is_numeric($n) ? number_format((float)$n, 2, '.', '') : $n;
    $currency = $order['currency'] ?? 'AED';
@endphp

Order Confirmed ✅
Order #{{ $order['reference'] ?? $order['id'] ?? '' }}

Date: {{ $order['createdAt'] ?? '' }}
@if($order['paidAt'] ?? null)
Paid: {{ $order['paidAt'] }}
@endif

Items:
@foreach($items as $item)
    @php
        $itemName = $item['productName'] ?? $item['name'] ?? 'Product';
        $itemQty = $item['quantity'] ?? 1;
        $itemUnit = $item['unitPrice'] ?? $item['unit_price'] ?? null;
        $itemSub  = $item['subtotal']  ?? $item['sub_total']  ?? 0;
        if (!is_numeric($itemUnit) || (float)$itemUnit <= 0) {
            $itemUnit = ((int)$itemQty > 0 && (float)$itemSub > 0)
                ? round((float)$itemSub / max(1, (int)$itemQty), 2)
                : 0;
        }
        $itemSku = $item['skuId'] ?? $item['sku_id'] ?? null;
    @endphp
    - {{ $itemName }}
    @if($itemSku)
        (SKU: {{ $itemSku }})
    @endif
    Quantity: {{ $itemQty }}
    Unit Price: {{ $fmt($itemUnit) }} {{ $currency }}
    Subtotal: {{ $fmt($itemSub) }} {{ $currency }}
@endforeach

TOTAL: {{ $fmt($order['amount'] ?? 0) }} {{ $currency }}

Payment Method:
@if($paymentMethod)
    @if($paymentMethod['brand'] ?? null)
        {{ ucfirst($paymentMethod['brand'] ?? 'Card') }} ending in {{ $paymentMethod['last4'] ?? '****' }}
    @else
        {{ ucfirst($paymentMethod['provider'] ?? 'Card') }} Payment
    @endif
@else
    Card Payment
@endif

Shipping Address:
@if($shippingAddress)
{{ $shippingAddress['name'] ?? '' }}@if($shippingAddress['lastName'] ?? null) {{ $shippingAddress['lastName'] }}@endif
{{ $shippingAddress['mobile'] ?? '' }}
{{ $shippingAddress['address'] ?? '' }}
@if($shippingAddress['additionalAddress'] ?? null){{ $shippingAddress['additionalAddress'] }}
@endif
{{ $shippingAddress['city'] ?? '' }}, {{ $shippingAddress['state'] ?? '' }} {{ $shippingAddress['zipCode'] ?? '' }}
@else
Not provided
@endif

@if($billingAddress && ($billingAddress['address'] ?? null) !== ($shippingAddress['address'] ?? null))
Billing Address:
{{ $billingAddress['name'] ?? '' }}@if($billingAddress['lastName'] ?? null) {{ $billingAddress['lastName'] }}@endif
{{ $billingAddress['mobile'] ?? '' }}
{{ $billingAddress['address'] ?? '' }}
@if($billingAddress['additionalAddress'] ?? null){{ $billingAddress['additionalAddress'] }}
@endif
{{ $billingAddress['city'] ?? '' }}, {{ $billingAddress['state'] ?? '' }} {{ $billingAddress['zipCode'] ?? '' }}
@endif

If you need help, contact us and mention order #{{ $order['reference'] ?? $order['id'] ?? '' }}.

@if(config('mail.review_url'))
{{ __('mail.review.five_stars_text') }}

Your feedback means a lot to us — please take a moment to leave a review here: {{ config('mail.review_url') }}
@endif

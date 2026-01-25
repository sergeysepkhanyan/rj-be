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
    - {{ $item['productName'] ?? 'Product' }}
    @if($item['skuId'] ?? null)
        (SKU: {{ $item['skuId'] }})
    @endif
    Quantity: {{ $item['quantity'] ?? 1 }}
    Unit Price: {{ $fmt($item['unitPrice'] ?? 0) }} {{ $currency }}
    Subtotal: {{ $fmt($item['subtotal'] ?? 0) }} {{ $currency }}
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

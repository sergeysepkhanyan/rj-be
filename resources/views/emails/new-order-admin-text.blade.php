@php
    $items = $order['items'] ?? [];
    $customer = $order['customer'] ?? [];
    $shippingAddress = $order['shippingAddress'] ?? null;
    $fmt = fn($n) => is_numeric($n) ? number_format((float)$n, 2, '.', '') : $n;
    $currency = $order['currency'] ?? 'AED';
@endphp
NEW ORDER RECEIVED
==================

Order #{{ $order['reference'] ?? $order['id'] ?? '' }}
Date: {{ $order['createdAt'] ?? '' }}

CUSTOMER INFORMATION
--------------------
Name: {{ $customer['name'] ?? 'N/A' }}
Email: {{ $customer['email'] ?? 'N/A' }}
Phone: {{ $customer['phone'] ?? 'N/A' }}

ORDER ITEMS
-----------
@foreach($items as $item)
- {{ $item['productName'] ?? 'Product' }}
  Qty: {{ $item['quantity'] ?? 1 }} × {{ $fmt($item['unitPrice'] ?? 0) }} {{ $currency }}
  Subtotal: {{ $fmt($item['subtotal'] ?? 0) }} {{ $currency }}

@endforeach

TOTAL: {{ $fmt($order['amount'] ?? 0) }} {{ $currency }}

@if($shippingAddress)
SHIPPING ADDRESS
----------------
{{ $shippingAddress['name'] ?? '' }}
{{ $shippingAddress['address'] ?? '' }}
{{ $shippingAddress['city'] ?? '' }}@if($shippingAddress['zipCode'] ?? null), {{ $shippingAddress['zipCode'] }}@endif

{{ $shippingAddress['country'] ?? '' }}
@endif

Please process this order promptly.
This is an automated notification from your store.

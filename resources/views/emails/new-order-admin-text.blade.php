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
@php
    $itemName = $item['productName'] ?? $item['name'] ?? 'Product';
    $itemQty  = $item['quantity']    ?? 1;
    $itemUnit = $item['unitPrice']   ?? $item['unit_price'] ?? null;
    $itemSub  = $item['subtotal']    ?? $item['sub_total']  ?? 0;
    if (!is_numeric($itemUnit) || (float)$itemUnit <= 0) {
        $itemUnit = ((int)$itemQty > 0 && (float)$itemSub > 0)
            ? round((float)$itemSub / max(1, (int)$itemQty), 2)
            : 0;
    }
@endphp
- {{ $itemName }}
  Qty: {{ $itemQty }} × {{ $fmt($itemUnit) }} {{ $currency }}
  Subtotal: {{ $fmt($itemSub) }} {{ $currency }}

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

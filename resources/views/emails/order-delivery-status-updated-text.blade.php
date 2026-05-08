@php
    $items = $order['items'] ?? [];
    $shippingAddress = $order['shippingAddress'] ?? null;
    $deliveryStatus = $order['deliveryStatus'] ?? '';
    $deliveryStatusLabel = $order['deliveryStatusLabel'] ?? ucfirst(str_replace('_', ' ', $deliveryStatus));
    $isDelivered = $deliveryStatus === 'delivered';
@endphp

@if($isDelivered)
Order Delivered 🎉
@elseif($deliveryStatus === 'out_for_delivery')
Your Order is Out for Delivery 🚚
@else
Order Status Updated
@endif

Order #{{ $order['reference'] ?? $order['id'] ?? '' }}

@if($isDelivered)
Your order has been delivered! Great news! Your order has been successfully delivered. We hope you enjoy your purchase.
@elseif($deliveryStatus === 'out_for_delivery')
Your order is on the way! Your order is now out for delivery and will arrive soon. Please make sure someone is available to receive it.
@else
Your order status has been updated to: {{ $deliveryStatusLabel }}
@endif

Current Status: {{ $deliveryStatusLabel }}

@if(count($items) > 0)
Order Items:
@foreach($items as $item)
    @php
        $itemName = $item['productName'] ?? $item['name'] ?? 'Product';
        $itemQty  = $item['quantity']    ?? 1;
    @endphp
    - {{ $itemName }}
      Quantity: {{ $itemQty }}
@endforeach
@endif

@if($shippingAddress)
Delivery Address:
{{ $shippingAddress['name'] ?? '' }}@if($shippingAddress['lastName'] ?? null) {{ $shippingAddress['lastName'] }}@endif
{{ $shippingAddress['address'] ?? '' }}
{{ $shippingAddress['city'] ?? '' }}, {{ $shippingAddress['state'] ?? '' }} {{ $shippingAddress['zipCode'] ?? '' }}
@endif

If you have any questions about your order, please contact us and mention order #{{ $order['reference'] ?? $order['id'] ?? '' }}.

@if($isDelivered && config('mail.review_url'))
⭐⭐⭐⭐⭐
Your feedback means a lot to us — please take a moment to leave a review here: {{ config('mail.review_url') }}
@endif

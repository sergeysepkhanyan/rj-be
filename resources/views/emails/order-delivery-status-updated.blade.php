@php
    $items = $order['items'] ?? [];
    $shippingAddress = $order['shippingAddress'] ?? null;
    $deliveryStatus = $order['deliveryStatus'] ?? '';
    $deliveryStatusLabel = $order['deliveryStatusLabel'] ?? ucfirst(str_replace('_', ' ', $deliveryStatus));
    $isDelivered = $deliveryStatus === 'delivered';
@endphp

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order Delivery Status Updated</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#111; color:#fff;">
                        @include('emails.partials.logo')
                        <div style="font-size:13px; opacity:0.9; margin-top:10px;">
                            @if($isDelivered)
                                🎉 Order Delivered
                            @elseif($deliveryStatus === 'out_for_delivery')
                                🚚 Out for Delivery
                            @else
                                📦 Order Status Updated
                            @endif
                            • Order #{{ $order['reference'] ?? $order['id'] ?? '' }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 24px;">
                        @if($isDelivered)
                            <div style="font-size:16px; font-weight:700; margin-bottom:8px;">
                                Your order has been delivered! 🎊
                            </div>
                            <div style="font-size:14px; line-height:1.6; color:#333; margin-bottom:18px;">
                                Great news! Your order has been successfully delivered. We hope you enjoy your purchase.
                            </div>
                        @elseif($deliveryStatus === 'out_for_delivery')
                            <div style="font-size:16px; font-weight:700; margin-bottom:8px;">
                                Your order is on the way! 🚚
                            </div>
                            <div style="font-size:14px; line-height:1.6; color:#333; margin-bottom:18px;">
                                Your order is now out for delivery and will arrive soon. Please make sure someone is available to receive it.
                            </div>
                        @else
                            <div style="font-size:16px; font-weight:700; margin-bottom:8px;">
                                Order Status: {{ $deliveryStatusLabel }}
                            </div>
                            <div style="font-size:14px; line-height:1.6; color:#333; margin-bottom:18px;">
                                Your order status has been updated to: <strong>{{ $deliveryStatusLabel }}</strong>
                            </div>
                        @endif

                        <div style="background:#f6f7fb; border-radius:12px; padding:16px; margin-bottom:18px;">
                            <div style="font-size:13px; color:#666; margin-bottom:6px;">Current Status</div>
                            <div style="font-size:16px; font-weight:700; color:#111;">{{ $deliveryStatusLabel }}</div>
                        </div>

                        @if(count($items) > 0)
                        <div style="font-size:15px; font-weight:700; margin-bottom:10px;">Order Items</div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; border-spacing:0 10px;">
                            @foreach($items as $item)
                                <tr>
                                    <td style="background:#f6f7fb; border-radius:12px; padding:14px 14px;">
                                        <div style="font-size:14px; font-weight:700; color:#111;">{{ $item['productName'] ?? 'Product' }}</div>
                                        <div style="font-size:12px; color:#555; margin-top:4px;">
                                            Quantity: {{ $item['quantity'] ?? 1 }}
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </table>
                        <div style="height:10px;"></div>
                        @endif

                        @if($shippingAddress)
                        <div style="font-size:15px; font-weight:700; margin-bottom:8px;">Delivery Address</div>
                        <div style="font-size:14px; color:#333; line-height:1.6; margin-bottom:18px;">
                            {{ $shippingAddress['name'] ?? '' }}@if($shippingAddress['lastName'] ?? null) {{ $shippingAddress['lastName'] }}@endif<br>
                            {{ $shippingAddress['address'] ?? '' }}<br>
                            {{ $shippingAddress['city'] ?? '' }}, {{ $shippingAddress['state'] ?? '' }} {{ $shippingAddress['zipCode'] ?? '' }}
                        </div>
                        @endif

                        <div style="height:18px;"></div>

                        <div style="font-size:12px; color:#666; line-height:1.6;">
                            If you have any questions about your order, please contact us and mention order #{{ $order['reference'] ?? $order['id'] ?? '' }}.
                        </div>

                        @if($isDelivered)
                            @include('emails.partials.review-request')
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666;">
                        Thank you for your purchase! 💛<br>
                        <span style="color:#999;">This is an automated status update email.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

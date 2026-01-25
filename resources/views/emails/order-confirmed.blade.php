@php
    $items = $order['items'] ?? [];
    $items = is_array($items) ? $items : (method_exists($items, 'all') ? $items->all() : []);
    $shippingAddress = $order['shippingAddress'] ?? null;
    $billingAddress = $order['billingAddress'] ?? null;
    $paymentMethod = $order['paymentMethod'] ?? null;

    $fmt = fn($n) => is_numeric($n) ? number_format((float)$n, 2, '.', '') : $n;
    $currency = $order['currency'] ?? 'AED';

    $subtotal = array_sum(array_column($items, 'subtotal'));
    $total = (float)($order['amount'] ?? $subtotal);
@endphp

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order confirmed</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#111; color:#fff;">
                        <div style="font-size:18px; font-weight:700;">✅ Order confirmed</div>
                        <div style="font-size:13px; opacity:0.9; margin-top:6px;">
                            Order #{{ $order['reference'] ?? $order['id'] ?? '' }} • {{ $order['createdAt'] ?? '' }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 24px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:8px;">
                            Thank you for your order! 👋
                        </div>
                        <div style="font-size:14px; line-height:1.6; color:#333;">
                            Your order has been confirmed. Below are the details of your purchase.
                        </div>

                        <div style="height:16px;"></div>

                        <div style="font-size:15px; font-weight:700; margin-bottom:10px;">Items</div>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; border-spacing:0 10px;">
                            @foreach($items as $item)
                                <tr>
                                    <td style="background:#f6f7fb; border-radius:12px; padding:14px 14px;">
                                        <div style="display:flex; justify-content:space-between; gap:12px;">
                                            <div style="flex:1;">
                                                <div style="font-size:14px; font-weight:700; color:#111;">{{ $item['productName'] ?? 'Product' }}</div>
                                                @if($item['skuId'] ?? null)
                                                <div style="font-size:12px; color:#555; margin-top:4px;">
                                                    SKU: {{ $item['skuId'] }}
                                                </div>
                                                @endif
                                                <div style="font-size:12px; color:#555; margin-top:8px;">
                                                    Quantity: {{ $item['quantity'] ?? 1 }} × {{ $fmt($item['unitPrice'] ?? 0) }} {{ $currency }}
                                                </div>
                                            </div>

                                            <div style="text-align:right; min-width:120px;">
                                                <div style="font-size:12px; color:#666;">Subtotal</div>
                                                <div style="font-size:16px; font-weight:800; color:#111;">{{ $fmt($item['subtotal'] ?? 0) }} {{ $currency }}</div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        <div style="height:10px;"></div>

                        <div style="border-top:1px solid #eee; margin:18px 0;"></div>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size:14px; color:#111; font-weight:800; padding-top:14px;">Total</td>
                                <td align="right" style="font-size:18px; color:#111; font-weight:900; padding-top:14px;">
                                    {{ $fmt($total) }} {{ $currency }}
                                </td>
                            </tr>
                        </table>

                        <div style="height:18px;"></div>

                        @if($paymentMethod)
                        <div style="font-size:15px; font-weight:700; margin-bottom:8px;">Payment Method</div>
                        <div style="font-size:14px; color:#333; margin-bottom:18px;">
                            @if($paymentMethod['brand'] ?? null)
                                {{ ucfirst($paymentMethod['brand'] ?? 'Card') }} ending in {{ $paymentMethod['last4'] ?? '****' }}
                            @else
                                {{ ucfirst($paymentMethod['provider'] ?? 'Card') }} Payment
                            @endif
                        </div>
                        @endif

                        @if($shippingAddress)
                        <div style="font-size:15px; font-weight:700; margin-bottom:8px;">Shipping Address</div>
                        <div style="font-size:14px; color:#333; line-height:1.6; margin-bottom:18px;">
                            {{ $shippingAddress['name'] ?? '' }}@if($shippingAddress['lastName'] ?? null) {{ $shippingAddress['lastName'] }}@endif<br>
                            {{ $shippingAddress['mobile'] ?? '' }}<br>
                            {{ $shippingAddress['address'] ?? '' }}<br>
                            @if($shippingAddress['additionalAddress'] ?? null){{ $shippingAddress['additionalAddress'] }}<br>@endif
                            {{ $shippingAddress['city'] ?? '' }}, {{ $shippingAddress['state'] ?? '' }} {{ $shippingAddress['zipCode'] ?? '' }}
                        </div>
                        @endif

                        @if($billingAddress && ($billingAddress['address'] ?? null) !== ($shippingAddress['address'] ?? null))
                        <div style="font-size:15px; font-weight:700; margin-bottom:8px;">Billing Address</div>
                        <div style="font-size:14px; color:#333; line-height:1.6; margin-bottom:18px;">
                            {{ $billingAddress['name'] ?? '' }}@if($billingAddress['lastName'] ?? null) {{ $billingAddress['lastName'] }}@endif<br>
                            {{ $billingAddress['mobile'] ?? '' }}<br>
                            {{ $billingAddress['address'] ?? '' }}<br>
                            @if($billingAddress['additionalAddress'] ?? null){{ $billingAddress['additionalAddress'] }}<br>@endif
                            {{ $billingAddress['city'] ?? '' }}, {{ $billingAddress['state'] ?? '' }} {{ $billingAddress['zipCode'] ?? '' }}
                        </div>
                        @endif

                        <div style="height:18px;"></div>

                        <div style="font-size:12px; color:#666; line-height:1.6;">
                            If you need help, contact us and mention order #{{ $order['reference'] ?? $order['id'] ?? '' }}.
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666;">
                        Thank you for your purchase! 💛<br>
                        <span style="color:#999;">This is an automated confirmation email.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

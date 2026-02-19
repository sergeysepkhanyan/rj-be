@php
    $items = $order['items'] ?? [];
    $customer = $order['customer'] ?? [];
    $shippingAddress = $order['shippingAddress'] ?? null;
    $fmt = fn($n) => is_numeric($n) ? number_format((float)$n, 2, '.', '') : $n;
    $currency = $order['currency'] ?? 'AED';
@endphp

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Order Received</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#2D5F3F; color:#fff;">
                        <div style="font-size:18px; font-weight:700;">🛒 New Order Received</div>
                        <div style="font-size:13px; opacity:0.9; margin-top:6px;">
                            Order #{{ $order['reference'] ?? $order['id'] ?? '' }} • {{ $order['createdAt'] ?? '' }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 24px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:16px;">
                            A new order has been placed!
                        </div>

                        <div style="background:#f6f7fb; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:14px; font-weight:700; margin-bottom:12px;">Customer Information</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Name:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $customer['name'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Email:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $customer['email'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Phone:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $customer['phone'] ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>

                        <div style="font-size:15px; font-weight:700; margin-bottom:10px;">Order Items</div>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; border-spacing:0 8px;">
                            @foreach($items as $item)
                                <tr>
                                    <td style="background:#f6f7fb; border-radius:10px; padding:12px;">
                                        <div style="display:flex; justify-content:space-between;">
                                            <div>
                                                <div style="font-size:14px; font-weight:600; color:#111;">{{ $item['productName'] ?? 'Product' }}</div>
                                                <div style="font-size:12px; color:#666; margin-top:4px;">
                                                    Qty: {{ $item['quantity'] ?? 1 }} × {{ $fmt($item['unitPrice'] ?? 0) }} {{ $currency }}
                                                </div>
                                            </div>
                                            <div style="text-align:right;">
                                                <div style="font-size:14px; font-weight:700; color:#111;">{{ $fmt($item['subtotal'] ?? 0) }} {{ $currency }}</div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        <div style="border-top:2px solid #2D5F3F; margin:16px 0; padding-top:16px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:16px; color:#111; font-weight:800;">Total Amount</td>
                                    <td align="right" style="font-size:20px; color:#2D5F3F; font-weight:900;">
                                        {{ $fmt($order['amount'] ?? 0) }} {{ $currency }}
                                    </td>
                                </tr>
                            </table>
                        </div>

                        @if($shippingAddress)
                        <div style="background:#f6f7fb; border-radius:12px; padding:16px; margin-top:16px;">
                            <div style="font-size:14px; font-weight:700; margin-bottom:8px;">Shipping Address</div>
                            <div style="font-size:13px; color:#333; line-height:1.6;">
                                {{ $shippingAddress['name'] ?? '' }}<br>
                                {{ $shippingAddress['address'] ?? '' }}<br>
                                {{ $shippingAddress['city'] ?? '' }}@if($shippingAddress['zipCode'] ?? null), {{ $shippingAddress['zipCode'] }}@endif<br>
                                {{ $shippingAddress['country'] ?? '' }}
                            </div>
                        </div>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666;">
                        Please process this order promptly.<br>
                        <span style="color:#999;">This is an automated notification from your store.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

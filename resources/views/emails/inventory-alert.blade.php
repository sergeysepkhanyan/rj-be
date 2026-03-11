<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory Alert</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#856404; color:#fff;">
                        @include('emails.partials.logo')
                        <div style="font-size:13px; opacity:0.9; margin-top:10px;">
                            ⚠️ {{ $totalAlerts }} product(s) need your attention
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 24px;">

                        @if(count($expiredProducts) > 0)
                        <div style="background:#F8D7DA; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:14px; font-weight:700; color:#721C24; margin-bottom:12px;">
                                🚫 Expired Products ({{ count($expiredProducts) }})
                            </div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                @foreach($expiredProducts as $product)
                                <tr>
                                    <td style="padding:8px 0; border-bottom:1px solid rgba(114,28,36,0.2);">
                                        <div style="font-size:14px; font-weight:600; color:#721C24;">{{ $product['name'] }}</div>
                                        <div style="font-size:12px; color:#721C24; opacity:0.8; margin-top:2px;">
                                            SKU: {{ $product['skuId'] ?? 'N/A' }} • Expired: {{ $product['expiryDate'] }}
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                        </div>
                        @endif

                        @if(count($expiringSoonProducts) > 0)
                        <div style="background:#FFF3CD; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:14px; font-weight:700; color:#856404; margin-bottom:12px;">
                                ⏰ Expiring Soon ({{ count($expiringSoonProducts) }})
                            </div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                @foreach($expiringSoonProducts as $product)
                                <tr>
                                    <td style="padding:8px 0; border-bottom:1px solid rgba(133,100,4,0.2);">
                                        <div style="font-size:14px; font-weight:600; color:#856404;">{{ $product['name'] }}</div>
                                        <div style="font-size:12px; color:#856404; opacity:0.8; margin-top:2px;">
                                            SKU: {{ $product['skuId'] ?? 'N/A' }} • Expires: {{ $product['expiryDate'] }} ({{ $product['daysUntilExpiry'] }} days)
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                        </div>
                        @endif

                        @if(count($lowStockProducts) > 0)
                        <div style="background:#FFE5D0; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:14px; font-weight:700; color:#C65102; margin-bottom:12px;">
                                📦 Low Stock ({{ count($lowStockProducts) }})
                            </div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                @foreach($lowStockProducts as $product)
                                <tr>
                                    <td style="padding:8px 0; border-bottom:1px solid rgba(198,81,2,0.2);">
                                        <div style="font-size:14px; font-weight:600; color:#C65102;">{{ $product['name'] }}</div>
                                        <div style="font-size:12px; color:#C65102; opacity:0.8; margin-top:2px;">
                                            SKU: {{ $product['skuId'] ?? 'N/A' }} • Stock: {{ $product['currentQuantity'] }} (Reorder at: {{ $product['reorderPoint'] }})
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </table>
                        </div>
                        @endif

                        <div style="margin-top:20px; font-size:13px; color:#666; line-height:1.6;">
                            Please review these items and take appropriate action to maintain your inventory.
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666;">
                        This is an automated inventory alert.<br>
                        <span style="color:#999;">Generated on {{ now()->format('d M Y, H:i') }}</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

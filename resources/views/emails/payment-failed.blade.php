@php
    $fmt = fn($n) => is_numeric($n) ? number_format((float)$n, 2, '.', '') : $n;
@endphp

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment Failed</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#DC3545; color:#fff;">
                        @include('emails.partials.logo')
                        <div style="font-size:13px; opacity:0.9; margin-top:10px;">
                            Payment Failed • Order {{ $data['orderReference'] ?? ('#' . ($data['orderId'] ?? '')) }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 24px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:8px;">
                            Hi {{ $data['customerName'] ?? 'there' }},
                        </div>
                        <div style="font-size:14px; line-height:1.6; color:#333;">
                            Unfortunately, your payment could not be processed. Please try again or use a different payment method.
                        </div>

                        <div style="height:16px;"></div>

                        <div style="background:#F8D7DA; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:14px; font-weight:700; color:#721C24; margin-bottom:8px;">Payment Issue</div>
                            <div style="font-size:13px; color:#721C24;">
                                {{ $data['failureReason'] ?? 'Payment could not be processed' }}
                            </div>
                        </div>

                        <div style="background:#f6f7fb; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:14px; font-weight:700; margin-bottom:12px;">Order Details</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Order Reference:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $data['orderReference'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Amount:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right; font-weight:700;">{{ $fmt($data['amount'] ?? 0) }} {{ $data['currency'] ?? 'AED' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Date:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $data['createdAt'] ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>

                        @if($data['booking'])
                        <div style="background:#f6f7fb; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:14px; font-weight:700; margin-bottom:12px;">Booking Details</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Service:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $data['booking']['serviceName'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Date:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $data['booking']['date'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Time:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $data['booking']['startTime'] ?? '' }} - {{ $data['booking']['endTime'] ?? '' }}</td>
                                </tr>
                            </table>
                        </div>
                        @endif

                        <div style="height:10px;"></div>

                        <div style="font-size:13px; color:#666; line-height:1.6;">
                            <strong>What you can do:</strong>
                            <ul style="margin:8px 0; padding-left:20px;">
                                <li>Check that your card details are correct</li>
                                <li>Ensure you have sufficient funds</li>
                                <li>Try a different payment method</li>
                                <li>Contact your bank if the problem persists</li>
                            </ul>
                        </div>

                        <div style="height:10px;"></div>

                        <div style="font-size:12px; color:#666; line-height:1.6;">
                            If you need assistance, please contact us with reference {{ $data['orderReference'] ?? ('#' . ($data['orderId'] ?? '')) }}.
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666;">
                        We apologize for any inconvenience.<br>
                        <span style="color:#999;">This is an automated notification.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

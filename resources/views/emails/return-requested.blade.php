<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Return Request</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#4C3715; color:#fff;">
                        @include('emails.partials.logo')
                        <div style="font-size:13px; opacity:0.92; margin-top:10px;">
                            Return Request - Order {{ $reference }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 24px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:8px;">
                            New Return Request
                        </div>

                        <div style="font-size:14px; line-height:1.6; color:#333;">
                            A return request has been submitted for order <strong>{{ $reference }}</strong>.
                        </div>

                        <div style="margin-top:16px; padding:14px; background:#f6f7fb; border-radius:10px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:13px; color:#555; padding-bottom:8px;">Customer</td>
                                    <td align="right" style="font-size:13px; color:#111; font-weight:700; padding-bottom:8px;">{{ $customerName }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#555; padding-bottom:8px;">Order Amount</td>
                                    <td align="right" style="font-size:13px; color:#111; font-weight:700; padding-bottom:8px;">{{ $currency }} {{ number_format((float)$orderAmount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#555; padding-bottom:8px;">Requested At</td>
                                    <td align="right" style="font-size:13px; color:#111; padding-bottom:8px;">{{ $createdAt }}</td>
                                </tr>
                            </table>
                        </div>

                        <div style="margin-top:16px;">
                            <div style="font-size:14px; font-weight:700; margin-bottom:6px;">Reason for Return</div>
                            <div style="font-size:13px; color:#333; padding:10px 12px; background:#f6f7fb; border-radius:10px; line-height:1.6;">
                                {{ $reason }}
                            </div>
                        </div>

                        <div style="height:20px;"></div>

                        <div style="font-size:12px; color:#666; line-height:1.6; text-align:center;">
                            Please review this return request in the admin panel.
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666; text-align:center;">
                        Romeo & Juliet Beauty Lounge<br>
                        <span style="color:#999;">This is an automated email.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

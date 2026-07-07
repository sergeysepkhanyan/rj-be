<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gift Card Balance Restored</title>
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
                            Gift Card Balance Restored
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 24px;">
                        <div style="font-size:18px; font-weight:700; margin-bottom:16px;">
                            Balance Restored
                        </div>

                        <div style="font-size:22px; font-weight:800; color:#111; margin-bottom:12px; text-align:center; padding:20px; background:#f6f7fb; border-radius:12px;">
                            <span style="color:#2e7d32;">{{ $amountRestored }} {{ $currency }}</span> has been credited back to your gift card.
                        </div>

                        <div style="font-size:15px; line-height:1.6; color:#333; margin-bottom:8px;">
                            <strong>Gift Card:</strong> {{ $code }}
                        </div>

                        <div style="font-size:15px; line-height:1.6; color:#333; margin-bottom:24px;">
                            <strong>Current Balance:</strong> {{ $remainingBalance }} {{ $currency }}
                        </div>

                        <div style="font-size:14px; line-height:1.6; color:#666;">
                            This happened because a purchase paid with this gift card was cancelled or refunded. The amount is available to spend again.
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666;">
                        Thank you!<br>
                        <span style="color:#999;">This is an automated notification email.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

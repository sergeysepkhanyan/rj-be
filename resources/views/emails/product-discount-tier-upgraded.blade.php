<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Store Discount Unlocked</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#111; color:#fff;">
                        @include('emails.partials.logo')
                        <div style="font-size:13px; opacity:0.9; margin-top:10px;">Store Discount Unlocked</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px 24px;">
                        <div style="font-size:16px; margin-bottom:8px;">Hi {{ $userName }} 👋</div>
                        <div style="font-size:22px; font-weight:800; margin:16px 0; text-align:center; padding:20px; background:#f6f7fb; border-radius:12px;">
                            🎉 You've unlocked a <span style="color:#2e7d32;">{{ $discount }}%</span> store discount!
                        </div>
                        <div style="font-size:15px; line-height:1.6; color:#333; margin-bottom:24px;">
                            Thanks for shopping with us. This discount is now applied automatically to your online store orders.
                        </div>
                        <div style="text-align:center;">
                            <a href="{{ $shopUrl }}" style="display:inline-block; padding:14px 32px; background:#111; color:#fff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:700;">Shop Now</a>
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

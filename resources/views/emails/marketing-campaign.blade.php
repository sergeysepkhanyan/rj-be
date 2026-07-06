<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('mail.from.name') }}</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#111; color:#fff;">
                        @include('emails.partials.logo')
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 24px;">
                        <div style="font-size:18px; font-weight:700; margin-bottom:16px;">
                            Hi {{ $recipientName }}
                        </div>

                        <div style="font-size:15px; line-height:1.7; color:#333; white-space:pre-line;">{{ $bodyText }}</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; border-top:1px solid #eee;">
                        <div style="font-size:12px; color:#888; line-height:1.6;">
                            You're receiving this because you opted in to offers and promotions.
                            <a href="{{ $unsubscribeUrl }}" style="color:#666; text-decoration:underline;">Unsubscribe</a>
                            from marketing emails at any time — your receipts, appointment
                            and reward notifications are not affected.
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

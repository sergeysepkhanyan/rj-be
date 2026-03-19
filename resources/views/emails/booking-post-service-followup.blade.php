@php
    $reference = $b['reference'] ?? ('#' . ($b['id'] ?? ''));
    $name = $b['customerName'] ?? 'there';
@endphp
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('mail.booking_post_service.subject', ['reference' => $reference]) }}</title>
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
                            {{ __('mail.booking_post_service.booking_ref', ['reference' => $reference]) }}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:22px 24px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:8px;">
                            {{ __('mail.booking_post_service.greeting', ['name' => $name]) }}
                        </div>
                        <div style="font-size:14px; line-height:1.6; color:#333;">
                            {{ __('mail.booking_post_service.lead') }}
                        </div>
                        <div style="height:18px;"></div>
                        @include('emails.partials.review-request')
                        <div style="height:18px;"></div>
                        <div style="font-size:12px; color:#666; line-height:1.6;">
                            {{ __('mail.booking_post_service.footer_help', ['reference' => $reference]) }}
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666;">
                        Thank you! 💛<br>
                        <span style="color:#999;">{{ __('mail.booking_post_service.footer_auto') }}</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

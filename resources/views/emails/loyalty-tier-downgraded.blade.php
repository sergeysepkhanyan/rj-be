<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Discount Level Update</title>
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
                            Loyalty Program Update
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 24px;">
                        <div style="font-size:18px; font-weight:700; margin-bottom:16px;">
                            Hi {{ $userName }}
                        </div>

                        @if($hasTier)
                            <div style="font-size:22px; font-weight:800; color:#111; margin-bottom:12px; text-align:center; padding:20px; background:#f6f7fb; border-radius:12px;">
                                Your discount level has changed to <span style="color:#b8860b;">{{ $tierName }}</span>.
                            </div>

                            <div style="font-size:15px; line-height:1.6; color:#333; margin-bottom:24px;">
                                You currently get <strong>{{ $tierValue }}% discount</strong> on all bookings. Keep booking to level up again!
                            </div>
                        @else
                            <div style="font-size:22px; font-weight:800; color:#111; margin-bottom:12px; text-align:center; padding:20px; background:#f6f7fb; border-radius:12px;">
                                Your discount level has been reset.
                            </div>

                            @if($firstTierName && $firstTierThreshold)
                                <div style="font-size:15px; line-height:1.6; color:#333; margin-bottom:24px;">
                                    Book <strong>{{ $firstTierThreshold }}</strong> more appointments to unlock <strong>{{ $firstTierName }}</strong>!
                                </div>
                            @else
                                <div style="font-size:15px; line-height:1.6; color:#333; margin-bottom:24px;">
                                    Keep booking to unlock discount levels!
                                </div>
                            @endif
                        @endif

                        <div style="text-align:center; margin-bottom:16px;">
                            <a href="{{ $bookingUrl }}" style="display:inline-block; padding:14px 32px; background:#111; color:#fff; text-decoration:none; border-radius:8px; font-size:14px; font-weight:700;">
                                Book Now
                            </a>
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

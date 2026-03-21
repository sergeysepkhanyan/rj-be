<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Return Approved</title>
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
                            Return Approved - Order {{ $reference }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 24px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:8px;">
                            Hi {{ $customerName }},
                        </div>

                        <div style="font-size:14px; line-height:1.6; color:#333;">
                            Your return request for order <strong>{{ $reference }}</strong> has been approved. A refund of <strong>{{ $currency }} {{ number_format((float)$orderAmount, 2) }}</strong> will be processed shortly.
                        </div>

                        @if($adminNotes)
                            <div style="margin-top:16px; padding:10px 12px; background:#f6f7fb; border-radius:10px; font-size:13px; color:#333;">
                                <strong>Note from our team:</strong> {{ $adminNotes }}
                            </div>
                        @endif

                        <div style="height:20px;"></div>

                        <div style="font-size:12px; color:#666; line-height:1.6; text-align:center;">
                            Questions? Contact us at <a href="mailto:info@rjbeautylounge.com" style="color:#4C3715;">info@rjbeautylounge.com</a>
                            or call <a href="tel:+971509039020" style="color:#4C3715;">+971 50 903 9020</a>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666; text-align:center;">
                        Thank you for choosing Romeo & Juliet Beauty Lounge<br>
                        <span style="color:#999;">This is an automated email.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

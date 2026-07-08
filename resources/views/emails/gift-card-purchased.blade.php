<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #4C3715; max-width: 600px; margin: 0 auto; }
        .header { text-align: center; padding: 30px 0; background-color: #FAF5EB; }
        .content { padding: 30px; }
        .code-box { background-color: #FAF5EB; border: 2px dashed #92846F; border-radius: 12px; padding: 20px; text-align: center; margin: 20px 0; }
        .code { font-size: 28px; font-weight: bold; color: #4C3715; letter-spacing: 2px; }
        .amount { font-size: 24px; font-weight: bold; color: #92846F; }
        .details { background-color: #f9f9f9; border-radius: 8px; padding: 15px; margin: 15px 0; }
        .footer { text-align: center; padding: 20px; font-size: 12px; color: #888; }
    </style>
</head>
<body>
    <div class="header">
        <img src="{{ config('mail.logo_url') ?? asset('images/rj.png') }}" alt="R&J Beauty Lounge" style="max-width:160px; height:auto; display:block; margin:0 auto 8px;">
        <p style="color: #92846F; margin: 5px 0 0;">Gift Card</p>
    </div>

    <div class="content">
        @if($type === 'buyer')
            <h2>Thank you for your purchase, {{ $buyerName }}!</h2>
            <p>Your gift card for <strong>{{ $recipientName }}</strong> is ready.</p>
        @else
            <h2>Hello {{ $recipientName }}!</h2>
            <p><strong>{{ $buyerName }}</strong> has sent you a gift card from R&J Beauty Lounge.</p>
        @endif

        <div class="code-box">
            <p style="margin: 0 0 10px; color: #92846F;">Gift Card Code</p>
            <div class="code">{{ $code }}</div>
            <div style="margin-top: 15px;">
                <span class="amount">{{ $amount }} {{ $currency }}</span>
            </div>
        </div>

        <div class="details">
            <p><strong>Recipient:</strong> {{ $recipientName }}</p>
            <p><strong>Valid Until:</strong> {{ $expiresAt }}</p>
            <p><strong>How to use:</strong> Present this code at R&J Beauty Lounge to redeem your gift card for any service or product.</p>
        </div>
    </div>

    <div class="footer">
        <p>&copy; {{ date('Y') }} R&J Beauty Lounge. All rights reserved.</p>
    </div>
</body>
</html>

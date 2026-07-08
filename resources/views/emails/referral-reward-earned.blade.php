<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Referral Reward Earned</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:32px 0;">
    <tr>
        <td align="center">
            <table width="600" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#111; color:#fff;">
                        @include('emails.partials.logo')
                        <div style="font-size:13px; opacity:0.9; margin-top:10px;">Referral Reward Earned</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px 36px;">
                        <h1 style="font-size:24px; margin:0 0 24px;">Congratulations, {{ $userName }}!</h1>
                        <p style="font-size:16px; color:#444; line-height:1.6; margin:0 0 16px;">
                            Thank you for referring your friends and family. Your referrals have earned you complimentary services!
                        </p>

                        <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0; border:1px solid #eee; border-radius:8px;">
                            <tr>
                                <td style="padding:16px; background:#f9f9f9; border-bottom:1px solid #eee;">
                                    <strong style="font-size:14px; color:#666;">Your New Rewards</strong>
                                </td>
                            </tr>
                            @foreach($rewardLines as $reward)
                                <tr>
                                    <td style="padding:12px 16px; border-bottom:1px solid #f0f0f0;">
                                        <span style="font-size:15px;">{{ $reward['serviceName'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        <p style="font-size:16px; color:#444; line-height:1.6; margin:16px 0 0;">
                            <strong>Your current balance:</strong> {{ $availableRewardsCount ?? 0 }} complimentary {{ ($availableRewardsCount ?? 0) === 1 ? 'reward' : 'rewards' }} ready to redeem.
                        </p>
                        <p style="font-size:16px; color:#444; line-height:1.6; margin:8px 0 0;">
                            Redeem in-store with our team, or online when you book — no account required. We look forward to seeing you soon!
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

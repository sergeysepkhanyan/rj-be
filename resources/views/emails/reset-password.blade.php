<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#111; color:#fff;">
                        <div style="font-size:18px; font-weight:700;">🔐 Reset Password</div>
                        <div style="font-size:13px; opacity:0.9; margin-top:6px;">
                            Romeo & Juliet Beauty Lounge
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 24px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:8px;">
                            Hi {{ $name ?: 'there' }},
                        </div>
                        <div style="font-size:14px; line-height:1.6; color:#333;">
                            We received a request to reset your password. Click the button below to create a new password.
                        </div>

                        <div style="height:16px;"></div>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td align="center" style="padding:12px 0;">
                                    <table role="presentation" cellpadding="0" cellspacing="0">
                                        <tr>
                                            <td style="background:#111; border-radius:10px;">
                                                <a href="{{ $resetUrl }}" style="display:inline-block; padding:12px 24px; color:#ffffff; text-decoration:none; font-size:14px; font-weight:700;">
                                                    Reset Password
                                                </a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        </table>

                        <div style="height:16px;"></div>

                        <div style="font-size:12px; color:#666; line-height:1.6;">
                            This link will expire in 60 minutes. If you didn't request a password reset, you can safely ignore this email.
                        </div>

                        <div style="height:16px;"></div>

                        <div style="font-size:12px; color:#666; line-height:1.6;">
                            If the button doesn't work, copy and paste this link into your browser:
                        </div>

                        <div style="font-size:11px; background:#f6f7fb; border-radius:10px; padding:12px; margin-top:8px; word-break:break-all; color:#555;">
                            {{ $resetUrl }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666;">
                        Thank you! 💛<br>
                        <span style="color:#999;">This is an automated email.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

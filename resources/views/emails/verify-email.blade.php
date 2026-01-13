<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f6f7fb;padding:24px 0;">
    <tr>
        <td align="center" style="padding:0 16px;">
            <table role="presentation" width="600" cellspacing="0" cellpadding="0" border="0"
                   style="width:600px;max-width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">
                <tr>
                    <td style="padding:22px 24px;background:#111827;color:#ffffff;">
                        <div style="font-size:16px;font-weight:700;">Romeo &amp; Juliet Beauty Lounge</div>
                        <div style="font-size:13px;opacity:.85;margin-top:4px;">Email verification</div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:28px 24px;">
                        <div style="font-size:18px;font-weight:700;margin:0 0 10px 0;">
                            Hi {{ $name ?: 'there' }} 👋
                        </div>

                        <div style="font-size:14px;line-height:22px;color:#374151;margin:0 0 16px 0;">
                            Thanks for registering with RJ. Please confirm your email address to activate your account.
                        </div>

                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:18px 0 10px 0;">
                            <tr>
                                <td align="center" bgcolor="#111827" style="border-radius:10px;">
                                    <a href="{{ $verifyUrl }}"
                                       style="display:inline-block;padding:12px 18px;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;">
                                        Verify Email
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <div style="font-size:12.5px;line-height:20px;color:#6b7280;margin:0 0 18px 0;">
                            This link expires in <strong>60 minutes</strong>.
                        </div>

                        <div style="font-size:12.5px;line-height:20px;color:#6b7280;margin:0 0 8px 0;">
                            If the button doesn’t work, copy and paste this link into your browser:
                        </div>

                        <div style="font-size:12px;line-height:18px;background:#f3f4f6;border:1px solid #e5e7eb;border-radius:10px;padding:12px;color:#111827;word-break:break-all;">
                            {{ $verifyUrl }}
                        </div>

                        <div style="font-size:12.5px;line-height:20px;color:#6b7280;margin:18px 0 0 0;">
                            If you didn’t create an account, you can safely ignore this email.
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px;background:#fafafa;border-top:1px solid #eef2f7;">
                        <div style="font-size:12px;color:#6b7280;">— RJ Team</div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:6px;">Please do not reply to this email.</div>
                    </td>
                </tr>
            </table>

            <div style="font-size:11px;line-height:16px;color:#9ca3af;margin-top:14px;text-align:center;">
                © {{ date('Y') }} Romeo &amp; Juliet Beauty Lounge
            </div>
        </td>
    </tr>
</table>
</body>
</html>


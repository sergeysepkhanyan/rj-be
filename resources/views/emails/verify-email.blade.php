<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
</head>
<body style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb;padding:24px 0;">
    <tr>
        <td align="center" style="padding:0 16px;">
            <table width="600" cellpadding="0" cellspacing="0"
                   style="max-width:100%;background:#ffffff;border-radius:16px;overflow:hidden;">

                <!-- Header -->
                <tr>
                    <td style="padding:22px 24px;background:#111827;color:#ffffff;">
                        @include('emails.partials.logo')
                        <div style="font-size:13px;opacity:.85;margin-top:10px;">Welcome to RJ</div>
                    </td>
                </tr>

                <!-- Body -->
                <tr>
                    <td style="padding:28px 24px;">
                        <div style="font-size:18px;font-weight:700;margin-bottom:10px;">
                            Hi {{ $name ?: 'there' }} 👋
                        </div>

                        <div style="font-size:14px;line-height:22px;color:#374151;margin-bottom:16px;">
                            Thanks for creating an account with RJ.
                            To finish setting things up, please confirm your email address.
                        </div>

                        <table cellpadding="0" cellspacing="0" style="margin:18px 0;">
                            <tr>
                                <td bgcolor="#111827" style="border-radius:10px;">
                                    <a href="{{ $verifyUrl }}"
                                       style="display:inline-block;padding:12px 18px;color:#ffffff;
                                                  text-decoration:none;font-size:14px;font-weight:700;">
                                        Confirm my account
                                    </a>
                                </td>
                            </tr>
                        </table>

                        <div style="font-size:12.5px;line-height:20px;color:#6b7280;">
                            This link will remain active for 60 minutes.
                        </div>

                        <div style="font-size:12.5px;line-height:20px;color:#6b7280;margin-top:16px;">
                            If the button doesn’t work, you can copy this link into your browser:
                        </div>

                        <div style="font-size:12px;background:#f3f4f6;border:1px solid #e5e7eb;
                                        border-radius:10px;padding:12px;margin-top:8px;word-break:break-all;">
                            {{ $verifyUrl }}
                        </div>

                        <div style="font-size:12.5px;color:#6b7280;margin-top:18px;">
                            If you didn’t create an account, you can safely ignore this email.
                        </div>
                    </td>
                </tr>

                <!-- Footer -->
                <tr>
                    <td style="padding:18px 24px;background:#fafafa;border-top:1px solid #eef2f7;">
                        <div style="font-size:12px;color:#6b7280;">— RJ Team</div>
                        <div style="font-size:11px;color:#9ca3af;margin-top:6px;">
                            Please do not reply to this email.
                        </div>
                    </td>
                </tr>
            </table>

            <div style="font-size:11px;color:#9ca3af;margin-top:14px;">
                © {{ date('Y') }} Romeo &amp; Juliet Beauty Lounge
            </div>
        </td>
    </tr>
</table>
</body>
</html>


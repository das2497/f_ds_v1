<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{ $subject ?? 'Shobley' }}</title>
</head>
<body style="margin:0;padding:0;background:#F8FAFC;font-family:'Segoe UI',Arial,sans-serif;color:#111827;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F8FAFC;padding:32px 16px;">
<tr><td align="center">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:14px;overflow:hidden;border:1px solid #E2E8F0;">
    <tr><td style="background:#111827;padding:20px 32px;">
        <span style="color:#FFFFFF;font-size:18px;font-weight:700;">Shobley</span>
    </td></tr>
    <tr><td style="padding:32px;">
        {{ $slot }}
    </td></tr>
    <tr><td style="padding:20px 32px;border-top:1px solid #E2E8F0;color:#64748B;font-size:12px;">
        &copy; {{ date('Y') }} Shobley. All rights reserved.<br>
        This is an automated message about your order — please don't reply to this email.
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>

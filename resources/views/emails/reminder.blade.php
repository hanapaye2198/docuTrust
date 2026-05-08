<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('DocuTrust Reminder') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="background:#0f766e;padding:20px 24px;color:#ffffff;font-size:20px;font-weight:700;">DocuTrust</td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 12px 0;font-size:16px;">{{ __('Hello :name,', ['name' => $recipientName]) }}</p>
                        <p style="margin:0 0 20px 0;font-size:14px;color:#4b5563;">{{ __('Friendly reminder: your signature is still needed for ":title".', ['title' => $documentTitle]) }}</p>
                        <p style="margin:0 0 20px 0;">
                            <a href="{{ $signUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:700;">{{ __('Sign Document Now') }}</a>
                        </p>
                        <p style="margin:0;font-size:13px;color:#4b5563;">{{ __('For your security, this link is unique to your signing session.') }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
                        {{ __('DocuTrust security and audit footer: this message is recorded as part of your document transaction history.') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('DocuTrust OTP') }}</title>
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
                        <p style="margin:0 0 16px 0;font-size:16px;">{{ __('Your one-time passcode') }}</p>
                        <p style="margin:0 0 18px 0;font-size:13px;color:#4b5563;">{{ __('Use this code to continue your secure DocuTrust session.') }}</p>
                        <p style="margin:0 0 18px 0;text-align:center;">
                            <span style="display:inline-block;padding:12px 20px;font-size:28px;letter-spacing:8px;font-weight:700;background:#ecfeff;color:#0f766e;border-radius:10px;">{{ $otp }}</span>
                        </p>
                        <p style="margin:0 0 8px 0;font-size:13px;color:#4b5563;">{{ __('This code expires in :minutes minutes.', ['minutes' => $expiresInMinutes]) }}</p>
                        <p style="margin:0;font-size:13px;color:#4b5563;">{{ __('Request type: :purpose', ['purpose' => str_replace('_', ' ', $purpose)]) }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
                        <strong>{{ __('Security notice:') }}</strong> {{ __('Never share this code. DocuTrust support will never ask for your OTP.') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

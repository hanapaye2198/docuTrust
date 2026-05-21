<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('DocuTrust e-Notary Invitation') }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f7fb;font-family:Arial,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#ffffff;border-radius:12px;overflow:hidden;">
                <tr>
                    <td style="background:#123629;padding:20px 24px;color:#f7f1e6;font-size:20px;font-weight:700;">DocuTrust e-Notary</td>
                </tr>
                <tr>
                    <td style="padding:24px;">
                        <p style="margin:0 0 12px 0;font-size:18px;font-weight:700;">{{ __('You are invited to join a notarization case') }}</p>
                        <p style="margin:0 0 8px 0;font-size:14px;color:#4b5563;">{{ __('Hello :name,', ['name' => $signerName]) }}</p>
                        <p style="margin:0 0 8px 0;font-size:14px;color:#4b5563;">{{ __(':attorney invited you to the e-Notary portal for this case:', ['attorney' => $attorneyName]) }}</p>
                        <p style="margin:0 0 20px 0;font-size:15px;font-weight:600;color:#123629;">{{ $caseTitle }}</p>
                        <p style="margin:0 0 20px 0;font-size:14px;color:#4b5563;">{{ __('Create your eNotary signer account to view the case, complete identity steps, and participate in the notarization session.') }}</p>
                        <p style="margin:0 0 20px 0;">
                            <a href="{{ $acceptUrl }}" style="display:inline-block;background:#123629;color:#f7f1e6;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:700;">{{ __('Accept invitation & set up account') }}</a>
                        </p>
                        <p style="margin:0 0 8px 0;font-size:13px;color:#4b5563;">{{ __('This invitation expires on :date.', ['date' => $expiresAt]) }}</p>
                        <p style="margin:0;font-size:13px;color:#4b5563;">{{ __('If you already have an e-Notary account, sign in with this email after opening the link.') }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
                        {{ __('This transactional email was sent by DocuTrust for secure e-Notary processing.') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

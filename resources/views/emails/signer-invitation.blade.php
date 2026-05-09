<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('DocuTrust Signature Invitation') }}</title>
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
                        <p style="margin:0 0 12px 0;font-size:18px;font-weight:700;">{{ __('You are invited to sign a document') }}</p>
                        <p style="margin:0 0 8px 0;font-size:14px;color:#4b5563;">{{ __('Document: :title', ['title' => $documentTitle]) }}</p>
                        <p style="margin:0 0 20px 0;font-size:14px;color:#4b5563;">{{ __('Sent by: :name', ['name' => $senderName]) }}</p>
                        <p style="margin:0 0 20px 0;">
                            <a href="{{ $signUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:700;">{{ __('Review and Sign Securely') }}</a>
                        </p>
                        @if ($requiresDocumentPassword)
                            <div style="margin:0 0 20px 0;padding:14px 16px;border-radius:10px;background:#fff7ed;border:1px solid #fdba74;">
                                <p style="margin:0 0 8px 0;font-size:13px;font-weight:700;color:#9a3412;">{{ __('This document requires a password before you can view or sign it.') }}</p>
                                @if (is_string($documentPasswordHint) && $documentPasswordHint !== '')
                                    <p style="margin:0;font-size:13px;color:#7c2d12;">{{ __('Password hint: :hint', ['hint' => $documentPasswordHint]) }}</p>
                                @else
                                    <p style="margin:0;font-size:13px;color:#7c2d12;">{{ __('Ask the sender for the document password if it was not shared with you separately.') }}</p>
                                @endif
                            </div>
                        @endif
                        <p style="margin:0 0 8px 0;font-size:13px;color:#4b5563;">{{ __('Expiration notice: this secure signing link may expire based on sender settings.') }}</p>
                        @if ($expiresAt)
                            <p style="margin:0 0 8px 0;font-size:13px;color:#4b5563;">{{ __('Link expiration: :date', ['date' => $expiresAt]) }}</p>
                        @endif
                        <p style="margin:0;font-size:13px;color:#4b5563;">{{ __('Security note: access this document only from trusted devices and networks.') }}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
                        {{ __('This transactional email was sent by DocuTrust for secure document processing and audit compliance.') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

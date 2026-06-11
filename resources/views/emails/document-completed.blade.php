<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('DocuTrust Document Completed') }}</title>
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
                        <p style="margin:0 0 12px 0;font-size:18px;font-weight:700;">{{ __('Document completed successfully') }}</p>
                        <p style="margin:0 0 10px 0;font-size:14px;color:#4b5563;">{{ __('Document: :title', ['title' => $document->title]) }}</p>
                        @if ($completedBy)
                            <p style="margin:0 0 10px 0;font-size:14px;color:#4b5563;">{{ __('Completed by: :name', ['name' => $completedBy]) }}</p>
                        @endif
                        <p style="margin:0 0 16px 0;font-size:14px;color:#4b5563;">{{ __('All required signatures are complete. Your signed document is ready.') }}</p>
                        @if (is_string($actionUrl) && $actionUrl !== '')
                            <p style="margin:0;">
                                <a href="{{ $actionUrl }}" style="display:inline-block;background:#0f766e;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:12px 20px;border-radius:10px;">
                                    {{ __('Download signed document') }}
                                </a>
                            </p>
                        @else
                            <p style="margin:0;font-size:14px;color:#4b5563;">{{ __('Sign in to DocuTrust to view and download your completed document.') }}</p>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
                        {{ __('Security footer: DocuTrust preserves completion timestamps and signing evidence for compliance review.') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>

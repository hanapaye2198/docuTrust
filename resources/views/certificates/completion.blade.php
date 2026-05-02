<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate of Completion</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; margin: 40px; }
        h1 { font-size: 28px; margin-bottom: 8px; }
        h2 { font-size: 16px; margin-top: 24px; margin-bottom: 8px; }
        p { margin: 4px 0; }
        .muted { color: #4b5563; }
        .box { border: 1px solid #d1d5db; padding: 14px; border-radius: 6px; }
    </style>
</head>
<body>
    <h1>Certificate of Completion</h1>
    <p class="muted">This certifies the document workflow was completed successfully.</p>

    <div class="box">
        <p><strong>Document Title:</strong> {{ $document->title }}</p>
        <p><strong>Completion Date:</strong> {{ $completedAt?->toDateTimeString() ?? 'N/A' }}</p>
        <p><strong>Document Hash (SHA-256):</strong> {{ $hash }}</p>
    </div>

    <h2>Signers</h2>
    @foreach ($signers as $signer)
        <p>{{ $signer->name }} - {{ ucfirst($signer->status->value) }} @if($signer->signed_at) ({{ $signer->signed_at->toDateTimeString() }}) @endif</p>
    @endforeach
</body>
</html>

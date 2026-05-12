<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 11px;
            line-height: 1.6;
            color: #1a1a1a;
            margin: 0;
            padding: 40px 50px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 0;
        }
        .header p {
            font-size: 10px;
            color: #555;
            margin: 5px 0 0;
        }
        .republic {
            text-align: center;
            font-size: 10px;
            margin-bottom: 20px;
        }
        .body-text {
            text-align: justify;
            margin-bottom: 15px;
        }
        .parties-list {
            margin: 10px 0 10px 20px;
        }
        .parties-list li {
            margin-bottom: 5px;
        }
        .evidence-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
            font-size: 10px;
        }
        .evidence-table th,
        .evidence-table td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: left;
        }
        .evidence-table th {
            background: #f5f5f5;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
        }
        .signature-block {
            margin-top: 50px;
            text-align: right;
            padding-right: 30px;
        }
        .signature-block .name {
            font-weight: bold;
            font-size: 12px;
            border-top: 1px solid #333;
            display: inline-block;
            padding-top: 5px;
            min-width: 200px;
            text-align: center;
        }
        .signature-block .title {
            font-size: 10px;
            color: #555;
            text-align: center;
        }
        .credentials {
            font-size: 9px;
            color: #666;
            text-align: right;
            margin-top: 10px;
            line-height: 1.8;
        }
        .entry-ref {
            margin-top: 30px;
            font-size: 10px;
            font-weight: bold;
        }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #ddd;
            padding-top: 15px;
            font-size: 9px;
            color: #888;
            text-align: center;
        }
        .qr-section {
            text-align: center;
            margin-top: 20px;
        }
        .qr-section img {
            width: 80px;
            height: 80px;
        }
        .qr-section p {
            font-size: 8px;
            color: #888;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Notarial Certificate</h1>
        <p>{{ config('app.name') }} — Digital Notarization Platform</p>
    </div>

    <div class="republic">
        <strong>REPUBLIC OF THE PHILIPPINES</strong><br>
        {{ $credential->commission_jurisdiction ?? 'Philippines' }}
    </div>

    @if ($entry->notarial_act_type === 'acknowledgment')
        <div class="body-text">
            <strong>ACKNOWLEDGMENT</strong><br><br>
            BEFORE ME, a Notary Public for and in the {{ $credential->commission_jurisdiction ?? 'Philippines' }},
            on this <strong>{{ $entry->notarized_at?->timezone('Asia/Manila')->format('jS \\d\\a\\y \\o\\f F, Y') }}</strong>
            at <strong>{{ $entry->notarized_at?->timezone('Asia/Manila')->format('g:i:s A') }} (PHT)</strong>,
            personally appeared:
        </div>
    @elseif ($entry->notarial_act_type === 'jurat')
        <div class="body-text">
            <strong>JURAT</strong><br><br>
            SUBSCRIBED AND SWORN to before me this
            <strong>{{ $entry->notarized_at?->timezone('Asia/Manila')->format('jS \\d\\a\\y \\o\\f F, Y') }}</strong>
            at <strong>{{ $entry->notarized_at?->timezone('Asia/Manila')->format('g:i:s A') }} (PHT)</strong>,
            affiant(s) exhibiting to me their competent evidence of identity:
        </div>
    @else
        <div class="body-text">
            <strong>{{ strtoupper(str_replace('_', ' ', $entry->notarial_act_type)) }}</strong><br><br>
            BEFORE ME, a Notary Public, on this
            <strong>{{ $entry->notarized_at?->timezone('Asia/Manila')->format('jS \\d\\a\\y \\o\\f F, Y') }}</strong>
            at <strong>{{ $entry->notarized_at?->timezone('Asia/Manila')->format('g:i:s A') }} (PHT)</strong>,
            personally appeared:
        </div>
    @endif

    <ol class="parties-list">
        @foreach ($entry->parties ?? [] as $party)
            <li>
                <strong>{{ $party['name'] ?? '-' }}</strong>
                @if (($party['address'] ?? '') !== '')
                    — {{ $party['address'] }}
                @endif
            </li>
        @endforeach
    </ol>

    <div class="body-text">
        known to me and to me known to be the same person(s) who executed the foregoing instrument
        titled <strong>"{{ $entry->document_title }}"</strong>
        and acknowledged to me that the same is their free and voluntary act and deed.
    </div>

    <table class="evidence-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>ID Type</th>
                <th>ID Number</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entry->competent_evidence ?? [] as $evidence)
                <tr>
                    <td>{{ $evidence['person_name'] ?? '-' }}</td>
                    <td>{{ $evidence['id_type'] ?? '-' }}</td>
                    <td>{{ $evidence['id_number'] ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if (($entry->witnesses ?? []) !== [])
        <div class="body-text">
            <strong>Witnesses:</strong>
        </div>
        <ol class="parties-list">
            @foreach ($entry->witnesses as $witness)
                <li>
                    {{ $witness['name'] ?? '-' }}
                    @if (($witness['address'] ?? '') !== '')
                        — {{ $witness['address'] }}
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    <div class="body-text">
        WITNESS MY HAND AND SEAL on the date and place above written.
    </div>

    <div class="signature-block">
        <div class="name">{{ $credential->user?->name ?? 'Notary Public' }}</div>
        <div class="title">Notary Public</div>
        <div class="credentials">
            Commission No. {{ $credential->commission_number }}<br>
            Valid Until {{ $credential->commission_expires_at?->format('F j, Y') }}<br>
            @if ($credential->roll_number)
                Roll No. {{ $credential->roll_number }}<br>
            @endif
            @if ($credential->ibp_number)
                IBP No. {{ $credential->ibp_number }}<br>
            @endif
            @if ($credential->ptr_number)
                PTR No. {{ $credential->ptr_number }}<br>
            @endif
            @if ($credential->mcle_compliance_number)
                MCLE Compliance No. {{ $credential->mcle_compliance_number }}<br>
            @endif
        </div>
    </div>

    <div class="entry-ref">
        {{ $entry->formattedReference() }}
        @if ($entry->official_receipt_number)
            <br>O.R. No. {{ $entry->official_receipt_number }}
            @if ((float) $entry->fees > 0)
                — ₱{{ number_format((float) $entry->fees, 2) }}
            @endif
        @endif
    </div>

    @if ($entry->qr_verification_token)
        <div class="qr-section">
            @if ($entry->qr_code_path)
                <img src="{{ storage_path('app/' . $entry->qr_code_path) }}" alt="QR Verification">
            @endif
            <p>Scan to verify this notarization online</p>
        </div>
    @endif

    <div class="footer">
        This notarial certificate was generated electronically by {{ config('app.name') }}.<br>
        Verification: {{ route('notary.verify', ['token' => $entry->qr_verification_token]) }}
    </div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>DocuTrust Compliance Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin-top: 18px; margin-bottom: 6px; }
        .muted { color: #555; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f5f5f5; }
        ul { margin: 4px 0; padding-left: 16px; }
    </style>
</head>
<body>
    <h1>DocuTrust Signature Compliance Report</h1>
    <p class="muted">
        Assessed: {{ $report['assessed_at'] ?? '' }} |
        Phase: {{ $report['phase'] ?? '' }} |
        Overall score: {{ $report['overall_score'] ?? 0 }}%
    </p>

    <h2>Trust classification</h2>
    <p>
        <strong>Level {{ $report['trust_level']['level'] ?? 1 }}:</strong>
        {{ $report['trust_level']['label'] ?? '' }}
    </p>
    <p class="muted">{{ $report['trust_level']['description'] ?? '' }}</p>
    @if (! empty($report['trust_level']['cap_reason']))
        <p class="muted">{{ $report['trust_level']['cap_reason'] }}</p>
    @endif

    <h2>Categories</h2>
    <table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Status</th>
                <th>Score</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report['categories'] ?? [] as $category)
                <tr>
                    <td>{{ $category['title'] ?? '' }}</td>
                    <td>{{ $category['status'] ?? '' }}</td>
                    <td>
                        @if (($category['status'] ?? '') === 'DISABLED')
                            N/A
                        @else
                            {{ $category['score_percentage'] ?? 0 }}%
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Supported standards</h2>
    <ul>
        @foreach ($report['standards_supported'] ?? [] as $standard)
            <li>{{ $standard }}</li>
        @endforeach
    </ul>

    <h2>Missing standards</h2>
    <ul>
        @foreach ($report['standards_missing'] ?? [] as $standard)
            <li>{{ $standard }}</li>
        @endforeach
    </ul>

    <h2>Recommendations</h2>
    <ul>
        @foreach ($report['recommendations'] ?? [] as $recommendation)
            <li>{{ $recommendation }}</li>
        @endforeach
    </ul>
</body>
</html>

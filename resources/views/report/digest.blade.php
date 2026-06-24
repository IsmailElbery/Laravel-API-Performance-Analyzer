<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1e293b; font-size: 12px; }
        h1 { font-size: 20px; margin-bottom: 2px; }
        h2 { font-size: 15px; margin-top: 24px; border-bottom: 2px solid #e2e8f0; padding-bottom: 4px; }
        .muted { color: #64748b; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { text-align: left; color: #64748b; font-size: 10px; text-transform: uppercase; border-bottom: 1px solid #e2e8f0; padding: 4px; }
        td { padding: 4px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 10px; }
        .cards td { width: 25%; border: 1px solid #e2e8f0; text-align: center; padding: 10px; }
        .big { font-size: 18px; font-weight: bold; }
        .rec { color: #0f766e; }
        .problem { font-weight: bold; }
    </style>
</head>
<body>
    <h1>API Performance Digest</h1>
    <div class="muted">
        {{ $window['from']->toDayDateTimeString() }} — {{ $window['to']->toDayDateTimeString() }}
        · generated {{ $generated_at->toDayDateTimeString() }}
    </div>

    <h2>1. Summary</h2>
    <table class="cards">
        <tr>
            <td><div class="big">{{ number_format($overview['total_requests']) }}</div>requests</td>
            <td><div class="big">{{ $overview['p95_ms'] }} ms</div>p95 duration</td>
            <td><div class="big">{{ round($overview['error_rate'] * 100, 2) }}%</div>error rate</td>
            <td><div class="big">{{ number_format($overview['n_plus_one_count']) }}</div>N+1 profiles</td>
        </tr>
    </table>

    <h2>2. Problems &amp; 3. Recommendations</h2>
    <p class="muted">Each problem is paired with its advisory fix. Recommendations are advisory only — verify before applying.</p>
    <table>
        <thead>
            <tr><th>Endpoint</th><th>Count</th><th>p95</th><th>Errors</th><th>Recommendation</th></tr>
        </thead>
        <tbody>
            @forelse($problems as $p)
                <tr>
                    <td class="mono problem">{{ $p['endpoint']['method'] }} {{ $p['endpoint']['uri'] }}</td>
                    <td>{{ number_format($p['endpoint']['count']) }}</td>
                    <td>{{ $p['endpoint']['p95_ms'] }} ms</td>
                    <td>{{ round($p['endpoint']['error_rate'] * 100, 1) }}%</td>
                    <td class="rec">
                        @foreach($p['recommendations'] as $r)
                            • {{ $r }}<br>
                        @endforeach
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No endpoints captured in this window.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Most frequent slow queries</h2>
    <table>
        <thead><tr><th>Sample SQL</th><th>Freq</th><th>Avg time</th></tr></thead>
        <tbody>
            @forelse($slow_queries as $q)
                <tr>
                    <td class="mono">{{ \Illuminate\Support\Str::limit($q->sample_sql, 140) }}</td>
                    <td>{{ number_format($q->frequency) }}</td>
                    <td>{{ round($q->avg_time_ms, 1) }} ms</td>
                </tr>
            @empty
                <tr><td colspan="3" class="muted">No slow queries recorded.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

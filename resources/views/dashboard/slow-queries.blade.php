@extends('apa::dashboard.layout')
@section('title', '· Slow queries')

@section('content')
    <h1 class="text-lg font-semibold mb-1">Slow queries</h1>
    <p class="text-sm text-slate-400 mb-4">Slow statements grouped across requests by SQL fingerprint (≥ {{ config('apa.thresholds.slow_query_ms') }} ms).</p>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">Sample SQL</th>
                    <th class="px-4 py-2 font-medium text-right">Frequency</th>
                    <th class="px-4 py-2 font-medium text-right">Avg time</th>
                    <th class="px-4 py-2 font-medium text-right">Max time</th>
                </tr>
            </thead>
            <tbody>
                @forelse($queries as $q)
                    <tr class="border-b border-slate-50 hover:bg-slate-50">
                        <td class="px-4 py-2 font-mono text-xs break-all max-w-xl">{{ $q->sample_sql }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($q->frequency) }}</td>
                        <td class="px-4 py-2 text-right font-semibold text-amber-700">{{ round($q->avg_time_ms, 1) }} ms</td>
                        <td class="px-4 py-2 text-right">{{ round($q->max_time_ms, 1) }} ms</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No slow queries recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

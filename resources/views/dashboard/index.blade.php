@extends('apa::dashboard.layout')
@section('title', '· Overview')

@section('content')
    <h1 class="text-lg font-semibold mb-4">Overview <span class="text-sm font-normal text-slate-400">(last 24h)</span></h1>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        @include('apa::dashboard.partials.stat', ['label' => 'Requests', 'value' => number_format($overview['total_requests'])])
        @include('apa::dashboard.partials.stat', ['label' => 'p95 duration', 'value' => $overview['p95_ms'].' ms', 'tone' => 'indigo'])
        @include('apa::dashboard.partials.stat', ['label' => 'Avg duration', 'value' => $overview['avg_ms'].' ms'])
        @include('apa::dashboard.partials.stat', ['label' => 'Error rate', 'value' => round($overview['error_rate'] * 100, 2).'%', 'tone' => $overview['error_rate'] > 0.02 ? 'red' : 'green'])
        @include('apa::dashboard.partials.stat', ['label' => 'Slow requests', 'value' => number_format($overview['slow_count']), 'tone' => 'amber'])
        @include('apa::dashboard.partials.stat', ['label' => 'N+1 profiles', 'value' => number_format($overview['n_plus_one_count']), 'tone' => 'rose'])
        @include('apa::dashboard.partials.stat', ['label' => 'Avg queries/req', 'value' => $overview['avg_queries']])
    </div>

    <div class="bg-white rounded-xl border border-slate-200">
        <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <h2 class="font-semibold">Slowest endpoints (by p95)</h2>
            <a href="{{ route('apa.endpoints') }}" class="text-sm text-indigo-600 hover:underline">All endpoints →</a>
        </div>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">Endpoint</th>
                    <th class="px-4 py-2 font-medium text-right">Count</th>
                    <th class="px-4 py-2 font-medium text-right">Avg</th>
                    <th class="px-4 py-2 font-medium text-right">p95</th>
                    <th class="px-4 py-2 font-medium text-right">Errors</th>
                    <th class="px-4 py-2 font-medium text-right">N+1</th>
                </tr>
            </thead>
            <tbody>
                @forelse($slowEndpoints as $e)
                    <tr class="border-b border-slate-50 hover:bg-slate-50">
                        <td class="px-4 py-2 font-mono text-xs">
                            <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 mr-1">{{ $e['method'] }}</span>
                            {{ $e['uri'] }}
                        </td>
                        <td class="px-4 py-2 text-right">{{ number_format($e['count']) }}</td>
                        <td class="px-4 py-2 text-right">{{ $e['avg_ms'] }} ms</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ $e['p95_ms'] }} ms</td>
                        <td class="px-4 py-2 text-right {{ $e['error_rate'] > 0.02 ? 'text-red-600' : '' }}">{{ round($e['error_rate'] * 100, 1) }}%</td>
                        <td class="px-4 py-2 text-right">{{ $e['n_plus_one_count'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No data captured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

@extends('apa::dashboard.layout')
@section('title', '· N+1 suspects')

@section('content')
    <h1 class="text-lg font-semibold mb-1">N+1 suspects</h1>
    <p class="text-sm text-slate-400 mb-4">Endpoints whose requests repeated the same statement more than the configured threshold ({{ config('apa.thresholds.n_plus_one') }}×).</p>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">Endpoint</th>
                    <th class="px-4 py-2 font-medium text-right">Occurrences</th>
                    <th class="px-4 py-2 font-medium text-right">Avg queries</th>
                    <th class="px-4 py-2 font-medium text-right">Avg duration</th>
                </tr>
            </thead>
            <tbody>
                @forelse($suspects as $s)
                    <tr class="border-b border-slate-50 hover:bg-slate-50">
                        <td class="px-4 py-2 font-mono text-xs">
                            <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 mr-1">{{ $s->method }}</span>{{ $s->uri }}
                        </td>
                        <td class="px-4 py-2 text-right font-semibold text-rose-600">{{ number_format($s->occurrences) }}</td>
                        <td class="px-4 py-2 text-right">{{ round($s->avg_queries, 1) }}</td>
                        <td class="px-4 py-2 text-right">{{ round($s->avg_ms, 1) }} ms</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No N+1 patterns detected.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

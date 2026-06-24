@extends('apa::dashboard.layout')
@section('title', '· Endpoints')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h1 class="text-lg font-semibold">Endpoints</h1>
        <form method="GET" class="text-sm">
            <label class="text-slate-500 mr-2">Sort by</label>
            <select name="sort" onchange="this.form.submit()" class="border border-slate-200 rounded px-2 py-1">
                @foreach(['p95' => 'p95 duration', 'avg' => 'avg duration', 'count' => 'most called', 'error_rate' => 'error rate', 'queries' => 'avg queries'] as $key => $label)
                    <option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">Endpoint</th>
                    <th class="px-4 py-2 font-medium text-right">Count</th>
                    <th class="px-4 py-2 font-medium text-right">Avg</th>
                    <th class="px-4 py-2 font-medium text-right">p95</th>
                    <th class="px-4 py-2 font-medium text-right">Error rate</th>
                    <th class="px-4 py-2 font-medium text-right">Avg queries</th>
                    <th class="px-4 py-2 font-medium text-right">N+1</th>
                </tr>
            </thead>
            <tbody>
                @forelse($endpoints as $e)
                    <tr class="border-b border-slate-50 hover:bg-slate-50">
                        <td class="px-4 py-2 font-mono text-xs">
                            <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 mr-1">{{ $e['method'] }}</span>
                            {{ $e['uri'] }}
                        </td>
                        <td class="px-4 py-2 text-right">{{ number_format($e['count']) }}</td>
                        <td class="px-4 py-2 text-right">{{ $e['avg_ms'] }} ms</td>
                        <td class="px-4 py-2 text-right font-semibold">{{ $e['p95_ms'] }} ms</td>
                        <td class="px-4 py-2 text-right {{ $e['error_rate'] > 0.02 ? 'text-red-600' : '' }}">{{ round($e['error_rate'] * 100, 1) }}%</td>
                        <td class="px-4 py-2 text-right">{{ $e['avg_queries'] }}</td>
                        <td class="px-4 py-2 text-right">{{ $e['n_plus_one_count'] ?: '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No data captured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection

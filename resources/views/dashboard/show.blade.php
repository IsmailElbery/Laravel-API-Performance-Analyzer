@extends('apa::dashboard.layout')
@section('title', '· Request detail')

@section('content')
    <a href="{{ url()->previous() }}" class="text-sm text-indigo-600 hover:underline">← Back</a>

    <div class="flex items-center gap-3 mt-2 mb-4">
        <span class="inline-block px-2 py-1 rounded bg-slate-100 text-slate-700 font-mono text-sm">{{ $profile->method }}</span>
        <h1 class="text-lg font-semibold font-mono">{{ $profile->uri }}</h1>
        @if($profile->is_slow)<span class="px-2 py-0.5 rounded bg-amber-100 text-amber-700 text-xs">SLOW</span>@endif
        @if($profile->has_n_plus_one)<span class="px-2 py-0.5 rounded bg-rose-100 text-rose-700 text-xs">N+1</span>@endif
    </div>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
        @include('apa::dashboard.partials.stat', ['label' => 'Status', 'value' => $profile->status_code, 'tone' => $profile->status_code >= 500 ? 'red' : 'slate'])
        @include('apa::dashboard.partials.stat', ['label' => 'Duration', 'value' => round($profile->duration_ms, 1).' ms', 'tone' => 'indigo'])
        @include('apa::dashboard.partials.stat', ['label' => 'DB time', 'value' => round($profile->db_time_ms, 1).' ms', 'sub' => $profile->db_query_count.' queries'])
        @include('apa::dashboard.partials.stat', ['label' => 'Peak memory', 'value' => number_format($profile->peak_memory_kb / 1024, 1).' MB'])
        @include('apa::dashboard.partials.stat', ['label' => 'External', 'value' => round($profile->external_time_ms, 1).' ms', 'sub' => $profile->external_call_count.' calls'])
    </div>

    <div class="text-xs text-slate-400 mb-4 font-mono break-all">{{ $profile->raw_uri }} · {{ $profile->created_at }} · uuid {{ $profile->uuid }}</div>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden mb-6">
        <div class="px-4 py-3 border-b border-slate-100 font-semibold">Queries ({{ $profile->queries->count() }})</div>
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">SQL</th>
                    <th class="px-4 py-2 font-medium text-right">Time</th>
                    <th class="px-4 py-2 font-medium text-right">Conn</th>
                </tr>
            </thead>
            <tbody>
                @forelse($profile->queries as $q)
                    <tr class="border-b border-slate-50 {{ $q->is_slow ? 'bg-amber-50' : '' }}">
                        <td class="px-4 py-2 font-mono text-xs break-all max-w-xl">{{ $q->sql }}</td>
                        <td class="px-4 py-2 text-right {{ $q->is_slow ? 'text-amber-700 font-semibold' : '' }}">{{ $q->time_ms }} ms</td>
                        <td class="px-4 py-2 text-right text-slate-400 text-xs">{{ $q->connection }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-slate-400">No child query rows stored for this profile.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($profile->httpCalls->isNotEmpty())
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-100 font-semibold">External HTTP calls ({{ $profile->httpCalls->count() }})</div>
            <table class="w-full text-sm">
                <tbody>
                    @foreach($profile->httpCalls as $h)
                        <tr class="border-b border-slate-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ $h->method }} {{ $h->url }}</td>
                            <td class="px-4 py-2 text-right">{{ $h->status_code }}</td>
                            <td class="px-4 py-2 text-right">{{ round($h->duration_ms, 1) }} ms</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

@extends('apa::dashboard.layout')
@section('title', '· Requests')

@section('content')
    <h1 class="text-lg font-semibold mb-4">Requests</h1>

    <form method="GET" class="flex flex-wrap gap-2 mb-4 text-sm">
        <input name="method" value="{{ $filters['method'] ?? '' }}" placeholder="Method" class="border border-slate-200 rounded px-2 py-1 w-24">
        <input name="status" value="{{ $filters['status'] ?? '' }}" placeholder="Status" class="border border-slate-200 rounded px-2 py-1 w-24">
        <input name="uri" value="{{ $filters['uri'] ?? '' }}" placeholder="URI pattern" class="border border-slate-200 rounded px-2 py-1 w-64">
        <label class="flex items-center gap-1 px-2"><input type="checkbox" name="slow" value="1" @checked(!empty($filters['slow']))> slow only</label>
        <button class="bg-slate-900 text-white rounded px-3 py-1">Filter</button>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="text-left text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="px-4 py-2 font-medium">When</th>
                    <th class="px-4 py-2 font-medium">Endpoint</th>
                    <th class="px-4 py-2 font-medium text-right">Status</th>
                    <th class="px-4 py-2 font-medium text-right">Duration</th>
                    <th class="px-4 py-2 font-medium text-right">Queries</th>
                    <th class="px-4 py-2 font-medium text-right">Mem</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $r)
                    <tr class="border-b border-slate-50 hover:bg-slate-50 cursor-pointer" onclick="window.location='{{ route('apa.requests.show', $r->uuid) }}'">
                        <td class="px-4 py-2 text-slate-400 text-xs">{{ $r->created_at?->diffForHumans() }}</td>
                        <td class="px-4 py-2 font-mono text-xs">
                            <span class="inline-block px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 mr-1">{{ $r->method }}</span>{{ $r->uri }}
                            @if($r->has_n_plus_one)<span class="ml-1 text-rose-600">N+1</span>@endif
                        </td>
                        <td class="px-4 py-2 text-right {{ $r->status_code >= 500 ? 'text-red-600 font-semibold' : ($r->status_code >= 400 ? 'text-amber-600' : '') }}">{{ $r->status_code }}</td>
                        <td class="px-4 py-2 text-right {{ $r->is_slow ? 'text-amber-600 font-semibold' : '' }}">{{ round($r->duration_ms, 1) }} ms</td>
                        <td class="px-4 py-2 text-right">{{ $r->db_query_count }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($r->peak_memory_kb / 1024, 1) }} MB</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">No requests captured yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $requests->links() }}</div>
@endsection

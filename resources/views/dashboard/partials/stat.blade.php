@props(['label', 'value', 'sub' => null, 'tone' => 'slate'])
<div class="bg-white rounded-xl border border-slate-200 p-4">
    <div class="text-xs uppercase tracking-wide text-slate-400">{{ $label }}</div>
    <div class="mt-1 text-2xl font-semibold text-{{ $tone }}-700">{{ $value }}</div>
    @if($sub)<div class="text-xs text-slate-400 mt-1">{{ $sub }}</div>@endif
</div>

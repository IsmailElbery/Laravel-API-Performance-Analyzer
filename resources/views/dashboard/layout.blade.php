<!DOCTYPE html>
<html lang="en" class="bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>API Performance Analyzer @yield('title')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="text-slate-800 antialiased">
<div class="min-h-screen">
    <header class="bg-slate-900 text-white">
        <div class="max-w-7xl mx-auto px-4 py-3 flex items-center gap-6">
            <a href="{{ route('apa.dashboard') }}" class="font-semibold tracking-tight">⚡ API Performance Analyzer</a>
            <nav class="flex gap-4 text-sm text-slate-300">
                <a href="{{ route('apa.dashboard') }}" class="hover:text-white">Overview</a>
                <a href="{{ route('apa.requests') }}" class="hover:text-white">Requests</a>
                <a href="{{ route('apa.endpoints') }}" class="hover:text-white">Endpoints</a>
                <a href="{{ route('apa.n-plus-one') }}" class="hover:text-white">N+1</a>
                <a href="{{ route('apa.slow-queries') }}" class="hover:text-white">Slow queries</a>
            </nav>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-6">
        @yield('content')
    </main>

    <footer class="max-w-7xl mx-auto px-4 py-6 text-xs text-slate-400">
        ismailelbery/api-performance-analyzer — data on connection
        <code>{{ config('apa.storage.connection') ?? 'default' }}</code>,
        driver <code>{{ config('apa.storage.driver') }}</code>.
    </footer>
</div>
</body>
</html>

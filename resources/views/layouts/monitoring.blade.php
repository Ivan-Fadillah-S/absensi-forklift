<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Monitoring') - Absensi Forklift</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f4f3ee] text-[#1c1f24]">
@php
    $menu = [
        ['Dashboard harian', route('monitoring.dashboard'), request()->routeIs('monitoring.dashboard')],
        ['Rekap periode', route('monitoring.rekap'), request()->routeIs('monitoring.rekap*')],
        ['Asisten AI', route('monitoring.ai'), request()->routeIs('monitoring.ai*')],
        ['Data operator', route('monitoring.operator.index'), request()->routeIs('monitoring.operator*')],
        ['Area absen', route('monitoring.area'), request()->routeIs('monitoring.area*')],
    ];
@endphp

<div class="flex min-h-screen">
    <aside class="flex w-60 shrink-0 flex-col bg-[#1c1f24] p-4 text-white">
        <div class="mb-6">
            <p class="text-lg font-extrabold tracking-widest text-[#f5b800]">KARENU INDAH</p>
            <p class="text-xs text-gray-400">Absensi &amp; Timesheet Forklift</p>
        </div>

        <nav class="space-y-2">
            @foreach ($menu as [$label, $url, $aktif])
                @if ($url)
                    <a href="{{ $url }}"
                       class="block rounded-xl px-4 py-3 text-sm font-bold {{ $aktif ? 'bg-[#f5b800] text-[#1c1f24]' : 'text-gray-200 hover:bg-[#262a31]' }}">{{ $label }}</a>
                @else
                    <span class="block cursor-not-allowed rounded-xl px-4 py-3 text-sm font-bold text-gray-500">{{ $label }}</span>
                @endif
            @endforeach
        </nav>

        <div class="mt-auto rounded-xl bg-[#262a31] p-3">
            <p class="font-bold">{{ auth()->user()->name }}</p>
            <p class="mb-3 text-xs text-gray-400">Monitoring · {{ auth()->user()->site?->name }}</p>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="w-full rounded-xl border border-gray-500 py-2 text-sm font-semibold">Keluar</button>
            </form>
        </div>
    </aside>

    <main class="min-w-0 flex-1 p-6">
        @yield('content')
    </main>
</div>
</body>
</html>
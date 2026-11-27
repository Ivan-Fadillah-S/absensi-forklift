<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Riwayat - Absensi Forklift</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f4f3ee] pb-24 text-[#1c1f24]">
@php
    $durasi = fn ($m) => \App\Support\Fmt::durasi($m);
    $jam = fn ($t) => $t ? $t->format('H.i') : '--.--';
    $garis = 'font-bold text-amber-800 underline decoration-dotted underline-offset-4';
@endphp

<div class="mx-auto min-h-screen max-w-md">

    <header class="bg-[#1c1f24] px-5 pb-5 pt-6 text-white">
        <h1 class="text-2xl font-extrabold">Riwayat absensi</h1>
        <div class="mt-4 flex items-center justify-between">
            <a href="{{ route('operator.riwayat', ['bulan' => $prev]) }}"
               class="rounded-xl border border-gray-500 px-4 py-2 font-bold">‹</a>
            <p class="text-sm font-bold">{{ $labelPeriode }}</p>
            @if ($bisaNext)
                <a href="{{ route('operator.riwayat', ['bulan' => $next]) }}"
                   class="rounded-xl border border-gray-500 px-4 py-2 font-bold">›</a>
            @else
                <span class="rounded-xl border border-gray-700 px-4 py-2 font-bold text-gray-600">›</span>
            @endif
        </div>
    </header>

    <main class="space-y-4 px-4 py-4">

        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-2xl border border-stone-200 bg-white p-3">
                <p class="text-xs text-stone-500">Hari hadir</p>
                <p class="text-2xl font-extrabold">{{ $hariHadir }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-3">
                <p class="text-xs text-stone-500">Lembur</p>
                <p class="text-2xl font-extrabold">{{ $durasi($lemburMenit) }}</p>
            </div>
            <div class="rounded-2xl border border-stone-200 bg-white p-3">
                <p class="text-xs text-stone-500">Dikoreksi</p>
                <p class="text-2xl font-extrabold">{{ $dikoreksi->count() }}</p>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('operator.riwayat', ['bulan' => $bulan]) }}"
               class="rounded-full px-4 py-2 text-sm font-bold {{ ! $filter ? 'bg-[#1c1f24] text-white' : 'border-2 border-stone-300 bg-white' }}">
                Semua {{ $semua->count() }}
            </a>
            <a href="{{ route('operator.riwayat', ['bulan' => $bulan, 'filter' => 'dikoreksi']) }}"
               class="rounded-full px-4 py-2 text-sm font-bold {{ $filter ? 'bg-[#1c1f24] text-white' : 'border-2 border-stone-300 bg-white' }}">
                Dikoreksi {{ $dikoreksi->count() }}
            </a>
        </div>

        @forelse ($daftar as $a)
            @php
                $label = $a->statusLabel();
                $warna = match ($label) {
                    'Lengkap'       => 'bg-green-100 text-green-800',
                    'Dikoreksi'     => 'bg-amber-100 text-amber-800',
                    'Belum lengkap' => 'bg-red-100 text-red-800',
                    default         => 'bg-stone-200 text-stone-700',
                };
                $lembur = $a->overtimeMinutes();
            @endphp
            <div class="rounded-2xl border border-stone-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-bold">{{ $a->work_date->locale('id')->translatedFormat('D, j M') }}</p>

                        @if ($a->status !== 'hadir')
                            <p class="text-sm text-stone-500">Tidak masuk{{ $a->note ? ' · ' . $a->note : '' }}</p>
                        @else
                            <p class="text-sm text-stone-500">
                                Masuk <span class="{{ $a->check_in_source === 'monitoring' ? $garis : '' }}">{{ $jam($a->check_in_at) }}</span>
                                · Pulang <span class="{{ $a->check_out_source === 'monitoring' ? $garis : '' }}">{{ $jam($a->check_out_at) }}</span>
                            </p>
                            @if ($lembur > 0)
                                <p class="text-sm font-bold">
                                    Lembur {{ $durasi($lembur) }}
                                    <span class="font-normal text-stone-500">({{ $a->shiftEnd()->format('H.i') }}–{{ $a->shiftEnd()->copy()->addMinutes($lembur)->format('H.i') }})</span>
                                </p>
                            @endif
                        @endif
                    </div>
                    <span class="whitespace-nowrap rounded-lg px-3 py-1 text-xs font-bold {{ $warna }}">{{ $label }}</span>
                </div>
            </div>
        @empty
            <p class="py-6 text-center text-sm text-stone-500">Belum ada data pada periode ini.</p>
        @endforelse

        <p class="text-sm text-stone-500">Jam bergaris bawah diinput oleh monitoring. Absen yang dikoreksi tetap dihitung hadir.</p>
    </main>

    @include('partials.operator-nav', ['aktif' => 'riwayat'])
</div>
</body>
</html>
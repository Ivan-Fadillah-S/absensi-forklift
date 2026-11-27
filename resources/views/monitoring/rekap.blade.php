@extends('layouts.monitoring')

@section('title', 'Rekap periode')

@section('content')
@php
    $durasi = fn ($m) => \App\Support\Fmt::durasi($m);
    $angka = fn ($n) => number_format($n, 1, ',', '.');
@endphp

<div class="flex flex-wrap items-center justify-between gap-4">
    <h1 class="text-3xl font-extrabold">Rekap periode</h1>

    <div class="flex items-center gap-3">
        <span class="text-sm font-semibold">Format</span>
        <span class="rounded-xl border-2 border-[#1c1f24] bg-white px-3 py-2 text-sm font-bold">Excel (.xlsx)</span>
        <a href="{{ route('monitoring.rekap.export', ['bulan' => $bulan]) }}"
           class="rounded-xl bg-[#f5b800] px-4 py-2 font-extrabold">Unduh rekap</a>
    </div>
</div>

<div class="mt-4 flex items-center gap-2">
    <a href="{{ route('monitoring.rekap', ['bulan' => $prev]) }}"
       class="rounded-xl border-2 border-[#1c1f24] px-4 py-2 font-bold">‹</a>
    <span class="rounded-xl bg-white px-4 py-2 font-bold">{{ $labelPeriode }}</span>
    @if ($bisaNext)
        <a href="{{ route('monitoring.rekap', ['bulan' => $next]) }}"
           class="rounded-xl border-2 border-[#1c1f24] px-4 py-2 font-bold">›</a>
    @else
        <span class="rounded-xl border-2 border-stone-300 px-4 py-2 font-bold text-stone-300">›</span>
    @endif
</div>

@if ($belumFinal > 0)
    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-amber-300 bg-amber-50 p-4">
        <p class="text-sm"><span class="font-bold">{{ $belumFinal }} operator</span> masih punya lupa absen yang belum dikoreksi, jadi rekap belum final.</p>
        @if ($filter === 'lupa')
            <a href="{{ route('monitoring.rekap', ['bulan' => $bulan]) }}"
               class="rounded-xl border-2 border-[#1c1f24] bg-white px-4 py-2 text-sm font-bold">Tampilkan semua operator</a>
        @else
            <a href="{{ route('monitoring.rekap', ['bulan' => $bulan, 'filter' => 'lupa']) }}"
               class="rounded-xl border-2 border-[#1c1f24] bg-white px-4 py-2 text-sm font-bold">Lihat daftar lupa absen</a>
        @endif
    </div>
@endif

<section class="mt-4 overflow-x-auto rounded-2xl border border-stone-200 bg-white">
    <h2 class="border-b border-stone-200 p-4 font-bold">Rekap per operator</h2>

    <table class="w-full text-left text-sm">
        <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-500">
            <tr>
                <th class="px-4 py-3">Operator</th>
                <th class="px-4 py-3">Shift</th>
                <th class="px-4 py-3 text-right">Hadir</th>
                <th class="px-4 py-3 text-right">Jam normal</th>
                <th class="px-4 py-3 text-right">Lembur</th>
                <th class="px-4 py-3 text-right">Total jam</th>
                <th class="px-4 py-3 text-right">Cuti</th>
                <th class="px-4 py-3 text-right">Izin</th>
                <th class="px-4 py-3 text-right">Sakit</th>
                <th class="px-4 py-3">Lupa absen</th>
                <th class="px-4 py-3">Harian</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($baris as $r)
                <tr class="border-t border-stone-100">
                    <td class="px-4 py-3 font-bold">{{ $r['user']->name }}</td>
                    <td class="px-4 py-3">{{ $r['user']->shift?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right">{{ $r['hadir'] }}</td>
                    <td class="px-4 py-3 text-right">{{ $r['jamNormal'] }}</td>
                    <td class="px-4 py-3 text-right">{{ $r['lemburMenit'] > 0 ? $durasi($r['lemburMenit']) : '0' }}</td>
                    <td class="px-4 py-3 text-right font-bold">{{ $angka($r['totalJam']) }}</td>
                    <td class="px-4 py-3 text-right">{{ $r['cuti'] }}</td>
                    <td class="px-4 py-3 text-right">{{ $r['izin'] }}</td>
                    <td class="px-4 py-3 text-right">{{ $r['sakit'] }}</td>
                    <td class="px-4 py-3">
                        @if ($r['belum'] > 0)
                            <span class="rounded-md bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800">{{ $r['belum'] }} belum dikoreksi</span>
                            <a href="{{ route('monitoring.attendance.show', ['operator' => $r['user'], 'tanggal' => $r['tanggalBelum'][0]]) }}"
                               class="ml-1 text-xs font-bold underline">Koreksi</a>
                        @elseif ($r['dikoreksi'] > 0)
                            <span class="rounded-md bg-stone-200 px-2 py-1 text-xs font-bold text-stone-700">{{ $r['dikoreksi'] }} dikoreksi</span>
                        @else
                            <span class="rounded-md bg-green-100 px-2 py-1 text-xs font-bold text-green-800">Tidak ada</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        <a href="{{ route('monitoring.rekap.harian', ['operator' => $r['user'], 'bulan' => $bulan]) }}"
                           class="rounded-lg border-2 border-[#1c1f24] px-3 py-1.5 text-xs font-bold">Lihat harian</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" class="px-4 py-8 text-center text-stone-500">Tidak ada data.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="border-t border-stone-100 px-4 py-3 text-xs text-stone-500">
        Menampilkan {{ $baris->count() }} dari {{ $total }} operator · Jam normal = hadir × jam per hari sesuai sistem kerja.
    </p>
</section>
@endsection
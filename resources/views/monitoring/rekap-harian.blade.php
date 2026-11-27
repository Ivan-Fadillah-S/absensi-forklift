@extends('layouts.monitoring')

@section('title', 'Rekap harian')

@section('content')
@php
    $durasi = fn ($m) => \App\Support\Fmt::durasi($m);
    $garis = 'font-bold text-amber-800 underline decoration-dotted underline-offset-4';
@endphp

<a href="{{ route('monitoring.rekap', ['bulan' => $bulan]) }}" class="text-sm font-bold">‹ Kembali ke rekap</a>

<div class="mt-2 flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold">Rekap harian · {{ $operator->name }}</h1>
        <p class="text-sm text-stone-500">
            {{ $operator->shift?->name ?? 'Tanpa shift' }} · {{ $operator->workSystem?->name ?? 'Tanpa sistem kerja' }}
        </p>
    </div>

    <div class="flex items-center gap-2">
        <a href="{{ route('monitoring.rekap.harian', ['operator' => $operator, 'bulan' => $prev]) }}"
           class="rounded-xl border-2 border-[#1c1f24] px-4 py-2 font-bold">‹</a>
        <span class="rounded-xl bg-white px-4 py-2 font-bold">{{ $labelPeriode }}</span>
        @if ($bisaNext)
            <a href="{{ route('monitoring.rekap.harian', ['operator' => $operator, 'bulan' => $next]) }}"
               class="rounded-xl border-2 border-[#1c1f24] px-4 py-2 font-bold">›</a>
        @else
            <span class="rounded-xl border-2 border-stone-300 px-4 py-2 font-bold text-stone-300">›</span>
        @endif
    </div>
</div>

<section class="mt-4 overflow-x-auto rounded-2xl border border-stone-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-500">
            <tr>
                <th class="px-4 py-3">Tanggal</th>
                <th class="px-4 py-3">Masuk</th>
                <th class="px-4 py-3">Pulang</th>
                <th class="px-4 py-3">Lembur</th>
                <th class="px-4 py-3">Keterangan</th>
                <th class="px-4 py-3">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($hari as $h)
                @php
                    $d = $h['detail'];
                    $akhirPekan = $h['tgl']->isWeekend();
                @endphp
                <tr class="border-t border-stone-100 {{ $akhirPekan ? 'bg-stone-50 text-stone-400' : '' }}">
                    <td class="px-4 py-3 font-bold">{{ $h['tgl']->copy()->locale('id')->translatedFormat('D, j M') }}</td>

                    @if ($d)
                        <td class="px-4 py-3"><span class="{{ $d['masukOleh'] === 'monitoring' ? $garis : '' }}">{{ $d['masuk'] ?? '--.--' }}</span></td>
                        <td class="px-4 py-3"><span class="{{ $d['pulangOleh'] === 'monitoring' ? $garis : '' }}">{{ $d['pulang'] ?? '--.--' }}</span></td>
                        <td class="px-4 py-3">
                            {{ $d['lemburMenit'] > 0 ? $durasi($d['lemburMenit']) . ' (' . $d['lemburMulai'] . '–' . $d['lemburSelesai'] . ')' : '—' }}
                        </td>
                        <td class="px-4 py-3">{{ $d['keterangan'] }}</td>
                    @else
                        <td class="px-4 py-3">--.--</td>
                        <td class="px-4 py-3">--.--</td>
                        <td class="px-4 py-3">—</td>
                        <td class="px-4 py-3">{{ $akhirPekan ? 'Akhir pekan, tidak ada data' : 'Tidak ada data' }}</td>
                    @endif

                    <td class="px-4 py-3">
                        <a href="{{ route('monitoring.attendance.show', ['operator' => $operator, 'tanggal' => $h['tgl']->format('Y-m-d')]) }}"
                           class="rounded-lg border-2 border-[#1c1f24] px-3 py-1.5 text-xs font-bold text-[#1c1f24]">{{ $d ? 'Ubah' : 'Isi' }}</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</section>

<p class="mt-3 text-sm text-stone-500">Jam bergaris bawah diinput oleh monitoring. Sistem belum punya kalender hari kerja, jadi hari tanpa data hanya ditandai, tidak dihitung alpa.</p>
@endsection
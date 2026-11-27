@extends('layouts.monitoring')

@section('title', 'Asisten AI')

@section('content')
@php
    $badge = ['tinggi' => 'bg-red-100 text-red-800', 'sedang' => 'bg-amber-100 text-amber-800'];
    $contoh = [
        'Operator 03 lupa absen pulang tanggal 6 Nov, pakai jam shift',
        'Operator 04 lupa absen masuk tanggal 6 Nov, jamnya 15.00',
        'Operator 07 izin tanggal 6 Nov karena keperluan keluarga',
    ];
@endphp

<h1 class="text-3xl font-extrabold">Asisten AI</h1>
<p class="text-sm text-stone-500">Membantu menyusun koreksi dan menemukan absen yang perlu diperiksa.</p>

{{-- 1. Asisten koreksi --}}
<section class="mt-4 rounded-2xl border border-stone-200 bg-white p-5">
    <h2 class="text-lg font-bold">Asisten koreksi</h2>
    <p class="text-sm text-stone-500">
        Tulis perintah dengan bahasa sehari-hari. AI hanya menyusun draf. Tidak ada yang tersimpan sampai kamu menekan
        <span class="font-bold">Simpan koreksi</span> di halaman berikutnya.
    </p>

    @if (session('ai_tanya'))
        <div class="mt-3 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm font-semibold">{{ session('ai_tanya') }}</div>
    @endif

    @error('perintah')
        <div class="mt-3 rounded-xl bg-red-100 p-3 text-sm font-semibold text-red-800">{{ $message }}</div>
    @enderror

    <form method="POST" action="{{ route('monitoring.ai.koreksi') }}" class="mt-3 space-y-3">
        @csrf
        <textarea id="perintah" name="perintah" rows="3" maxlength="400" required
                  placeholder="Contoh: Operator 03 lupa absen pulang tanggal 6 Nov, pakai jam shift"
                  class="w-full rounded-xl border-2 border-stone-300 px-3 py-3 text-base">{{ old('perintah') }}</textarea>

        <div class="flex flex-wrap gap-2">
            @foreach ($contoh as $c)
                <button type="button" data-teks="{{ $c }}"
                        class="contoh rounded-full border-2 border-stone-300 px-3 py-1.5 text-xs font-bold">{{ $c }}</button>
            @endforeach
        </div>

        <button id="kirim" class="rounded-xl bg-[#f5b800] px-5 py-3 font-extrabold">Susun draf koreksi</button>
    </form>
</section>

{{-- 2. Deteksi anomali --}}
<section class="mt-4 rounded-2xl border border-stone-200 bg-white p-5">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold">Deteksi anomali absen</h2>
            <p class="text-sm text-stone-500">Dua lapis: aturan tetap dan model statistik (machine learning tak terawasi) yang mempelajari pola normal seluruh operator. Penanda untuk diperiksa, bukan bukti kecurangan.</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('monitoring.ai', ['bulan' => $prev]) }}"
               class="rounded-xl border-2 border-[#1c1f24] px-3 py-2 font-bold">‹</a>
            <span class="rounded-xl bg-stone-100 px-3 py-2 text-sm font-bold">{{ $labelPeriode }}</span>
            @if ($bisaNext)
                <a href="{{ route('monitoring.ai', ['bulan' => $next]) }}"
                   class="rounded-xl border-2 border-[#1c1f24] px-3 py-2 font-bold">›</a>
            @else
                <span class="rounded-xl border-2 border-stone-300 px-3 py-2 font-bold text-stone-300">›</span>
            @endif
        </div>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-2xl border border-stone-200 p-4">
            <p class="text-sm text-stone-600">Total temuan</p>
            <p class="text-4xl font-extrabold">{{ count($temuan) }}</p>
        </div>
        <div class="rounded-2xl border border-stone-200 p-4">
            <p class="text-sm text-stone-600">Tingkat tinggi</p>
            <p class="text-4xl font-extrabold text-red-700">{{ $tinggi }}</p>
        </div>
        <div class="rounded-2xl border border-stone-200 p-4">
            <p class="text-sm text-stone-600">Tingkat sedang</p>
            <p class="text-4xl font-extrabold text-amber-700">{{ $sedang }}</p>
        </div>
        <div class="rounded-2xl border border-stone-200 p-4">
            <p class="text-sm text-stone-600">Operator terdampak</p>
            <p class="text-4xl font-extrabold">{{ $operatorKena }}</p>
        </div>
    </div>

    <form method="POST" action="{{ route('monitoring.ai.ringkas') }}" class="mt-4">
        @csrf
        <input type="hidden" name="bulan" value="{{ $bulan }}">
        <button id="ringkas" class="rounded-xl border-2 border-[#1c1f24] px-4 py-2 text-sm font-bold">Ringkas dengan AI</button>
    </form>

    @error('ringkasan')
        <div class="mt-3 rounded-xl bg-red-100 p-3 text-sm font-semibold text-red-800">{{ $message }}</div>
    @enderror

    @if (session('ringkasan'))
        <div class="mt-3 whitespace-pre-line rounded-xl border border-sky-300 bg-sky-50 p-4 text-sm">{{ session('ringkasan') }}</div>
    @endif

    <div class="mt-4 overflow-x-auto rounded-xl border border-stone-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Tanggal</th>
                    <th class="px-4 py-3">Operator</th>
                    <th class="px-4 py-3">Tingkat</th>
                    <th class="px-4 py-3">Temuan</th>
                    <th class="px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($temuan as $t)
                    <tr class="border-t border-stone-100 align-top">
                        <td class="whitespace-nowrap px-4 py-3">{{ \Carbon\Carbon::parse($t['tanggal'])->locale('id')->translatedFormat('D, j M') }}</td>
                        <td class="px-4 py-3 font-bold">{{ $t['operator'] }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-md px-2 py-1 text-xs font-bold {{ $badge[$t['tingkat']] }}">{{ ucfirst($t['tingkat']) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <p class="font-bold">{{ $t['aturan'] }}</p>
                            <p class="text-stone-600">{{ $t['pesan'] }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <a href="{{ route('monitoring.attendance.show', ['operator' => $t['user'], 'tanggal' => $t['tanggal']]) }}"
                               class="whitespace-nowrap rounded-lg border-2 border-[#1c1f24] px-3 py-1.5 text-xs font-bold">Periksa</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-stone-500">Tidak ada temuan pada periode ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<script>
    document.querySelectorAll('.contoh').forEach((tombol) => {
        tombol.addEventListener('click', () => {
            document.getElementById('perintah').value = tombol.dataset.teks;
        });
    });

    document.querySelector('form[action="{{ route('monitoring.ai.koreksi') }}"]').addEventListener('submit', () => {
        const k = document.getElementById('kirim');
        k.disabled = true;
        k.textContent = 'AI sedang menyusun draf...';
    });

    document.querySelector('form[action="{{ route('monitoring.ai.ringkas') }}"]').addEventListener('submit', () => {
        const k = document.getElementById('ringkas');
        k.disabled = true;
        k.textContent = 'AI sedang meringkas...';
    });
</script>
@endsection
@extends('layouts.monitoring')

@section('title', 'Dashboard harian')

@section('content')
@php
    $jam = fn ($t) => $t ? $t->format('H.i') : '--.--';
    $garis = 'font-bold text-amber-800 underline decoration-dotted underline-offset-4';

    $warna = [
        'lengkap'     => 'bg-green-100 text-green-800',
        'lupa_masuk'  => 'bg-amber-100 text-amber-800',
        'lupa_pulang' => 'bg-amber-100 text-amber-800',
        'belum'       => 'bg-stone-200 text-stone-700',
        'izin'        => 'bg-sky-100 text-sky-800',
        'bekerja'     => 'bg-blue-100 text-blue-800',
        'menunggu'    => 'bg-stone-100 text-stone-500',
    ];

    $kartu = [
        'lengkap' => ['Lengkap', 'masuk dan pulang tercatat', 'text-green-700'],
        'lupa'    => ['Lupa absen', 'perlu dikoreksi', 'text-amber-700'],
        'luar'    => ['Di luar area', 'lokasi di luar radius pabrik', 'text-red-700'],
        'belum'   => ['Belum absen', 'tanpa data masuk', 'text-stone-700'],
        'izin'    => ['Cuti/izin/sakit', 'keterangan dari operator', 'text-sky-700'],
    ];

    $q = fn (array $tambah = []) => array_filter(
        array_merge(['tanggal' => $tanggal->format('Y-m-d')], $tambah)
    );
@endphp

<div class="flex flex-wrap items-start justify-between gap-4">
    <div>
        <h1 class="text-3xl font-extrabold">Dashboard absensi harian</h1>
        <p class="text-sm text-stone-500">{{ auth()->user()->site?->name }} · {{ $total }} operator forklift</p>
    </div>

    <div class="flex items-center gap-2">
        <a href="{{ route('monitoring.dashboard', ['tanggal' => $prev]) }}"
           class="rounded-xl border-2 border-[#1c1f24] px-4 py-2 font-bold">‹</a>

        <form method="GET" action="{{ route('monitoring.dashboard') }}">
            <input type="date" name="tanggal" value="{{ $tanggal->format('Y-m-d') }}"
                   max="{{ now()->format('Y-m-d') }}" onchange="this.form.submit()"
                   class="rounded-xl border-2 border-[#1c1f24] bg-white px-3 py-2 font-bold">
        </form>

        @if ($bisaNext)
            <a href="{{ route('monitoring.dashboard', ['tanggal' => $next]) }}"
               class="rounded-xl border-2 border-[#1c1f24] px-4 py-2 font-bold">›</a>
        @else
            <span class="rounded-xl border-2 border-stone-300 px-4 py-2 font-bold text-stone-300">›</span>
        @endif
    </div>
</div>

<p class="mt-4 text-sm text-stone-500">Klik kartu untuk menyaring daftar di bawah. Klik lagi untuk membatalkan.</p>

<div class="mt-3 grid grid-cols-2 gap-3 lg:grid-cols-5">
    @foreach ($kartu as $kunci => [$judul, $ket, $warnaAngka])
        <a href="{{ route('monitoring.dashboard', $q(['filter' => $filter === $kunci ? null : $kunci])) }}"
           class="rounded-2xl bg-white p-4 {{ $filter === $kunci ? 'border-2 border-[#1c1f24]' : 'border border-stone-200' }}">
            <p class="text-sm text-stone-600">{{ $judul }}</p>
            <p class="text-4xl font-extrabold {{ $warnaAngka }}">{{ $hitung[$kunci] }}</p>
            <p class="text-xs text-stone-500">{{ $ket }}</p>
        </a>
    @endforeach
</div>

<section class="mt-4 overflow-x-auto rounded-2xl border border-stone-200 bg-white">
    <h2 class="border-b border-stone-200 p-4 font-bold">
        Status operator · {{ $tanggal->locale('id')->translatedFormat('l, j M Y') }}
    </h2>

    <table class="w-full text-left text-sm">
        <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-500">
            <tr>
                <th class="px-4 py-3">Operator</th>
                <th class="px-4 py-3">Shift</th>
                <th class="px-4 py-3">Masuk</th>
                <th class="px-4 py-3">Pulang</th>
                <th class="px-4 py-3">Jarak ke pabrik</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($tampil as $r)
                <tr class="border-t border-stone-100">
                    <td class="px-4 py-3 font-bold">{{ $r['user']->name }}</td>
                    <td class="px-4 py-3">{{ $r['user']->shift?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <span class="{{ $r['a']?->check_in_source === 'monitoring' ? $garis : '' }}">{{ $jam($r['a']?->check_in_at) }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="{{ $r['a']?->check_out_source === 'monitoring' ? $garis : '' }}">{{ $jam($r['a']?->check_out_at) }}</span>
                    </td>
                    <td class="px-4 py-3 {{ $r['luar'] ? 'font-bold text-red-700' : '' }}">
                        {{ \App\Support\Fmt::jarak($r['jarak']) }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="rounded-md px-2 py-1 text-xs font-bold {{ $warna[$r['kode']] }}">{{ $r['label'] }}</span>
                        @if ($r['luar'])
                            <span class="ml-1 rounded-md bg-red-100 px-2 py-1 text-xs font-bold text-red-800">Di luar area</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @php($url = route('monitoring.attendance.show', ['operator' => $r['user'], 'tanggal' => $tanggal->format('Y-m-d')]))
                        @if (in_array($r['kode'], ['lupa_masuk', 'lupa_pulang', 'belum'], true))
                            <a href="{{ $url }}" class="rounded-lg bg-[#f5b800] px-3 py-1.5 text-xs font-bold">Koreksi</a>
                        @else
                            <a href="{{ $url }}" class="rounded-lg border-2 border-[#1c1f24] px-3 py-1.5 text-xs font-bold">Detail</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-stone-500">Tidak ada operator pada kategori ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="border-t border-stone-100 px-4 py-3 text-xs text-stone-500">
        Menampilkan {{ $tampil->count() }} dari {{ $total }} operator
    </p>
</section>
@endsection
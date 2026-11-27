@extends('layouts.monitoring')

@section('title', 'Detail dan koreksi')

@section('content')
@php
    $tglStr = $tgl->format('Y-m-d');
    $titik = fn ($t) => str_replace(':', '.', $t);
    $mulaiShift = $jendela ? $jendela[0]->format('H:i') : null;
    $akhirShift = $jendela ? $jendela[1]->format('H:i') : null;

    $warna = [
        'lengkap'     => 'bg-green-100 text-green-800',
        'lupa_masuk'  => 'bg-amber-100 text-amber-800',
        'lupa_pulang' => 'bg-amber-100 text-amber-800',
        'belum'       => 'bg-stone-200 text-stone-700',
        'izin'        => 'bg-sky-100 text-sky-800',
        'bekerja'     => 'bg-blue-100 text-blue-800',
        'menunggu'    => 'bg-stone-100 text-stone-500',
    ];

    $ketMasuk = match ($attendance?->check_in_source) {
        'operator'   => 'Dicatat operator, ada foto dan lokasi.',
        'monitoring' => 'Diinput monitoring.',
        default      => 'Belum ada data.',
    };
    $ketPulang = match ($attendance?->check_out_source) {
        'operator'   => 'Dicatat operator, ada foto dan lokasi.',
        'monitoring' => 'Diinput monitoring.',
        default      => 'Belum ada data.',
    };

    $catatanLama = $attendance && $attendance->status !== 'hadir' ? $attendance->note : '';
@endphp

<a href="{{ route('monitoring.dashboard', ['tanggal' => $tglStr]) }}" class="text-sm font-bold">‹ Kembali ke dashboard</a>

<div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-3xl font-extrabold">Detail &amp; koreksi absensi</h1>
        <p class="text-sm text-stone-500">
            {{ $operator->name }} · {{ $operator->shift?->name ?? 'Tanpa shift' }}
            @if ($jendela)
                ({{ $titik($mulaiShift) }}–{{ $titik($akhirShift) }})
            @endif
            · {{ $tgl->locale('id')->translatedFormat('l, j M Y') }}
        </p>
    </div>
    <span class="rounded-lg px-3 py-1 text-sm font-bold {{ $warna[$kode] }}">{{ $label }}</span>
</div>

@if (session('status'))
    <div class="mt-4 rounded-xl bg-green-100 p-3 text-sm font-semibold text-green-800">{{ session('status') }}</div>
@endif

@if (session('ai'))
    <div class="mt-4 rounded-xl border border-sky-300 bg-sky-50 p-3 text-sm font-semibold text-sky-900">Asisten AI: {{ session('ai') }}</div>
@endif

@if ($errors->any())
    <div class="mt-4 rounded-xl bg-red-100 p-3 text-sm font-semibold text-red-800">
        @foreach ($errors->all() as $pesan)
            <p>{{ $pesan }}</p>
        @endforeach
    </div>
@endif

<div class="mt-4 grid gap-4 lg:grid-cols-5">

    <div class="space-y-4 lg:col-span-3">

        <form method="POST" action="{{ route('monitoring.attendance.update', ['operator' => $operator, 'tanggal' => $tglStr]) }}"
              class="space-y-4 rounded-2xl border border-stone-200 bg-white p-5">
            @csrf
            @method('PUT')

            <h2 class="text-lg font-bold">Jam absen</h2>

            <fieldset>
                <legend class="mb-2 text-sm font-semibold">Status hari itu</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach (['hadir' => 'Hadir', 'cuti' => 'Cuti', 'izin' => 'Izin', 'sakit' => 'Sakit'] as $nilai => $nama)
                        <label class="cursor-pointer">
                            <input type="radio" name="status" value="{{ $nilai }}" class="peer sr-only"
                                   @checked(old('status', $attendance?->status ?? 'hadir') === $nilai)>
                            <span class="block rounded-full border-2 border-stone-300 bg-white px-4 py-2 text-sm font-bold peer-checked:border-[#1c1f24] peer-checked:bg-[#1c1f24] peer-checked:text-white">{{ $nama }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="check_in" class="mb-1 block text-sm font-semibold">Absen masuk</label>
                    <input id="check_in" name="check_in" type="time"
                           value="{{ old('check_in', $attendance?->check_in_at?->format('H:i')) }}"
                           class="w-full rounded-xl border-2 border-stone-300 px-3 py-3 text-base">
                    <p class="mt-1 text-xs text-stone-500">{{ $ketMasuk }}</p>
                    @if ($mulaiShift)
                        <button type="button" onclick="document.getElementById('check_in').value = '{{ $mulaiShift }}'"
                                class="mt-2 rounded-lg border-2 border-[#1c1f24] px-3 py-1.5 text-xs font-bold">
                            Pakai jam shift ({{ $titik($mulaiShift) }})
                        </button>
                    @endif
                </div>

                <div>
                    <label for="check_out" class="mb-1 block text-sm font-semibold">Absen pulang</label>
                    <input id="check_out" name="check_out" type="time"
                           value="{{ old('check_out', $attendance?->check_out_at?->format('H:i')) }}"
                           class="w-full rounded-xl border-2 border-stone-300 px-3 py-3 text-base">
                    <p class="mt-1 text-xs text-stone-500">{{ $ketPulang }}</p>
                    @if ($akhirShift)
                        <button type="button" onclick="document.getElementById('check_out').value = '{{ $akhirShift }}'"
                                class="mt-2 rounded-lg border-2 border-[#1c1f24] px-3 py-1.5 text-xs font-bold">
                            Pakai jam shift ({{ $titik($akhirShift) }})
                        </button>
                    @endif
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="reason" class="mb-1 block text-sm font-semibold">Alasan koreksi (wajib)</label>
                    <select id="reason" name="reason" required
                            class="w-full rounded-xl border-2 border-stone-300 bg-white px-3 py-3 text-base">
                        <option value="">Pilih alasan...</option>
                        @foreach ($alasan as $a)
                            <option value="{{ $a }}" @selected(old('reason') === $a)>{{ $a }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="note" class="mb-1 block text-sm font-semibold">Catatan</label>
                    <input id="note" name="note" type="text" maxlength="200"
                           value="{{ old('note', $catatanLama) }}"
                           class="w-full rounded-xl border-2 border-stone-300 px-3 py-3 text-base">
                    <p class="mt-1 text-xs text-stone-500">Wajib untuk alasan "Lainnya" dan untuk cuti, izin, atau sakit.</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <button class="rounded-xl bg-[#f5b800] px-5 py-3 font-extrabold">Simpan koreksi</button>
                <a href="{{ route('monitoring.dashboard', ['tanggal' => $tglStr]) }}"
                   class="rounded-xl border-2 border-[#1c1f24] px-5 py-3 font-bold">Batal</a>
            </div>

            <p class="text-xs text-stone-500">
                Setelah disimpan, perubahan dicatat (siapa dan kapan) dan rekap otomatis diperbarui.
                Kolom jam yang dikosongkan berarti tidak diubah.
            </p>
        </form>

        <section class="rounded-2xl border border-stone-200 bg-white p-5">
            <h2 class="mb-3 text-lg font-bold">Riwayat perubahan</h2>

            @forelse ($riwayat as $r)
                <div class="flex gap-3 py-2">
                    <span class="mt-1.5 h-3 w-3 shrink-0 rounded-full {{ $r['koreksi'] ? 'bg-[#f5b800]' : 'bg-stone-400' }}"></span>
                    <div>
                        <p class="font-bold">{{ $r['judul'] }}</p>
                        <p class="text-sm text-stone-500">{{ $r['oleh'] }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-stone-500">Belum ada catatan untuk hari ini.</p>
            @endforelse
        </section>
    </div>

    <section class="space-y-4 rounded-2xl border border-stone-200 bg-white p-5 lg:col-span-2">
        <h2 class="text-lg font-bold">Bukti absen</h2>

        <div class="grid grid-cols-2 gap-2">
            @foreach (['masuk' => ['Masuk', $attendance?->check_in_at], 'pulang' => ['Pulang', $attendance?->check_out_at]] as $kunci => [$nama, $waktu])
                <a href="{{ route('monitoring.attendance.show', ['operator' => $operator, 'tanggal' => $tglStr, 'bukti' => $kunci]) }}"
                   class="rounded-xl border-2 py-2 text-center text-sm font-bold {{ $tab === $kunci ? 'border-[#1c1f24] bg-[#1c1f24] text-white' : 'border-stone-300' }}">
                    {{ $nama }} {{ $waktu ? $waktu->format('H.i') : '' }}
                </a>
            @endforeach
        </div>

        @if ($bukti)
            <div class="flex gap-4">
                <div class="h-40 w-32 shrink-0 overflow-hidden rounded-xl bg-stone-100">
                    @if ($bukti['photo'])
                        <img src="{{ asset('storage/' . $bukti['photo']) }}" alt="Foto wajah" class="h-full w-full object-cover">
                    @else
                        <div class="flex h-full items-center justify-center p-2 text-center text-xs text-stone-500">Tidak ada foto</div>
                    @endif
                </div>

                <div class="space-y-2">
                    @if ($bukti['distance'] !== null)
                        @if ($bukti['outside'])
                            <span class="inline-block rounded-md bg-red-100 px-2 py-1 text-xs font-bold text-red-800">Di luar area pabrik</span>
                        @else
                            <span class="inline-block rounded-md bg-green-100 px-2 py-1 text-xs font-bold text-green-800">Dalam area pabrik</span>
                        @endif
                    @endif

                    @if ($bukti['source'] === 'monitoring')
                        <p class="text-xs font-semibold text-amber-800">Jam ini diinput monitoring.</p>
                    @endif

                    <p class="text-xs text-stone-500">Foto tersimpan sebagai bukti, tanpa pencocokan wajah AI.</p>
                </div>
            </div>

            @php
                $baris = [
                    'Koordinat'           => $bukti['lat'] !== null ? number_format((float) $bukti['lat'], 6) . ', ' . number_format((float) $bukti['lng'], 6) : '—',
                    'Jarak dari pabrik'   => \App\Support\Fmt::jarak($bukti['distance']),
                    'Akurasi GPS'         => $bukti['accuracy'] !== null ? '±' . $bukti['accuracy'] . ' m' : '—',
                    'Waktu server'        => $bukti['source'] === 'operator' ? $bukti['waktu']->format('H.i') . ' WIB' : '—',
                ];
            @endphp

            <dl class="divide-y divide-stone-100 rounded-xl border border-stone-200 text-sm">
                @foreach ($baris as $nama => $isi)
                    <div class="flex justify-between gap-3 px-3 py-2">
                        <dt class="text-stone-500">{{ $nama }}</dt>
                        <dd class="font-bold">{{ $isi }}</dd>
                    </div>
                @endforeach
            </dl>

            @if ($bukti['lat'] !== null)
                <a href="https://www.google.com/maps?q={{ $bukti['lat'] }},{{ $bukti['lng'] }}" target="_blank" rel="noopener"
                   class="block rounded-xl border-2 border-[#1c1f24] py-2 text-center text-sm font-bold">Buka lokasi di Google Maps</a>
            @endif
        @else
            <p class="rounded-xl bg-stone-50 p-4 text-sm text-stone-500">Belum ada data absen {{ $tab }} pada hari ini.</p>
        @endif
    </section>
</div>
@endsection
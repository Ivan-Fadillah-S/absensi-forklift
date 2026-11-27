<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Absen - Absensi Forklift</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f4f3ee] pb-24 text-[#1c1f24]">
@php
    $jam = fn ($t) => str_replace(':', '.', substr($t, 0, 5));
    $labelState = ['belum' => 'Belum absen', 'bekerja' => 'Sedang bekerja', 'selesai' => 'Selesai'];
@endphp

<div class="mx-auto min-h-screen max-w-md">

        <header class="bg-[#1c1f24] px-5 pb-5 pt-5 text-white">
        <div class="flex items-center justify-between">
            <span class="text-sm font-extrabold tracking-widest text-[#f5b800]">KARENU INDAH</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="rounded-xl border border-gray-500 px-3 py-2 text-sm font-semibold">Keluar</button>
            </form>
        </div>

        <h1 class="mt-4 text-2xl font-extrabold">Halo, {{ $user->name }}</h1>
        <p class="mt-1 text-sm text-gray-300">
            {{ now()->locale('id')->translatedFormat('l, j M Y') }} · {{ $user->site->name }}
        </p>

        @if ($user->shift)
            <span class="mt-3 inline-block whitespace-nowrap rounded-full bg-[#f5b800] px-4 py-1.5 text-sm font-bold text-[#1c1f24]">
                {{ $user->shift->name }} · {{ $jam($user->shift->start_time) }}–{{ $jam($user->shift->end_time) }}
            </span>
        @endif
    </header>

    <main class="space-y-4 px-4 py-4">

        @if (session('status'))
            <div class="rounded-xl bg-green-100 p-3 text-sm font-semibold text-green-800">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl bg-red-100 p-3 text-sm font-semibold text-red-800">
                @foreach ($errors->all() as $pesan)
                    <p>{{ $pesan }}</p>
                @endforeach
            </div>
        @endif

        <section class="rounded-2xl border border-stone-200 bg-white p-4">
            <div class="flex items-start justify-between">
                <div>
                    <p class="text-sm text-stone-500">Waktu server</p>
                    <p id="clock" class="text-4xl font-extrabold">{{ now()->format('H.i') }}</p>
                </div>
                <span class="rounded-lg bg-stone-200 px-3 py-1 text-sm font-bold">{{ $labelState[$state] }}</span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-3">
                <div class="rounded-xl border border-stone-200 p-3">
                    <p class="text-sm text-stone-500">Masuk</p>
                    <p class="text-2xl font-bold">{{ $attendance?->check_in_at?->format('H.i') ?? '--.--' }}</p>
                </div>
                <div class="rounded-xl border border-stone-200 p-3">
                    <p class="text-sm text-stone-500">Pulang</p>
                    <p class="text-2xl font-bold">{{ $attendance?->check_out_at?->format('H.i') ?? '--.--' }}</p>
                </div>
            </div>

            @if ($attendance && $attendance->status !== 'hadir')
                <p class="mt-3 text-sm font-semibold text-amber-700">Hari ini tercatat: {{ ucfirst($attendance->status) }}</p>
            @endif
        </section>

        @if ($state !== 'selesai')
            <form id="absen-form" method="POST"
                  action="{{ route('operator.absen.store', $state === 'belum' ? 'masuk' : 'pulang') }}"
                  enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="lat" id="lat">
                <input type="hidden" name="lng" id="lng">
                <input type="hidden" name="accuracy" id="accuracy">
                <input type="file" name="photo" id="photo" accept="image/*" capture="user" class="hidden">

                <section class="rounded-2xl border border-stone-200 bg-white p-4">
                    <h2 class="mb-3 font-bold">Siapkan sebelum absen</h2>

                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p id="loc-title" class="font-bold text-stone-500">Mencari lokasi...</p>
                            <p id="loc-detail" class="text-sm text-stone-500">Izinkan akses lokasi di browser.</p>
                        </div>
                        <button type="button" id="loc-refresh"
                                class="rounded-xl border-2 border-[#1c1f24] px-3 py-2 text-sm font-bold">Perbarui</button>
                    </div>

                    <hr class="my-4 border-stone-200">

                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="font-bold">Foto wajah</p>
                            <p id="photo-detail" class="text-sm text-stone-500">Wajib sebagai bukti absen</p>
                        </div>
                        <label for="photo"
                               class="cursor-pointer rounded-xl bg-[#1c1f24] px-4 py-2 text-sm font-bold text-white">Ambil foto</label>
                    </div>
                    <img id="photo-preview" class="mt-3 hidden h-40 w-full rounded-xl object-cover" alt="Pratinjau foto">
                </section>

                <button id="submit" type="submit" disabled
                        class="w-full rounded-2xl bg-[#f5b800] py-4 text-lg font-extrabold disabled:bg-stone-200 disabled:text-stone-500">
                    {{ $state === 'belum' ? 'Absen Masuk' : 'Absen Pulang' }}
                </button>
                <p id="hint" class="text-center text-sm text-stone-500">Tunggu lokasi dan ambil foto wajah agar tombol aktif.</p>
            </form>
        @else
            <div class="rounded-2xl border border-stone-200 bg-white p-4 text-center font-semibold">
                Absen hari ini sudah lengkap. Terima kasih.
            </div>
        @endif
    </main>

     @include('partials.operator-nav', ['aktif' => 'absen'])
     
</div>

<script>
    // Jam server berjalan
    const selisih = new Date("{{ now()->toIso8601String() }}").getTime() - Date.now();
    function tick() {
        const d = new Date(Date.now() + selisih);
        document.getElementById('clock').textContent =
            d.toLocaleTimeString('id-ID', { timeZone: 'Asia/Jakarta', hour: '2-digit', minute: '2-digit', hour12: false })
             .replace(':', '.');
    }
    tick();
    setInterval(tick, 1000);
</script>

@if ($state !== 'selesai')
<script>
    const SITE = {
        lat: {{ (float) $user->site->latitude }},
        lng: {{ (float) $user->site->longitude }},
        radius: {{ (int) $user->site->radius_m }},
    };
    const el = (id) => document.getElementById(id);
    let siapLokasi = false;
    let siapFoto = false;

    // Hanya untuk tampilan. Angka resmi dihitung ulang oleh server.
    function haversine(lat1, lng1, lat2, lng2) {
        const R = 6371000, rad = (d) => d * Math.PI / 180;
        const dLat = rad(lat2 - lat1), dLng = rad(lng2 - lng1);
        const a = Math.sin(dLat / 2) ** 2 +
                  Math.cos(rad(lat1)) * Math.cos(rad(lat2)) * Math.sin(dLng / 2) ** 2;
        return Math.round(2 * R * Math.asin(Math.sqrt(a)));
    }

    function perbaruiTombol() {
        el('submit').disabled = !(siapLokasi && siapFoto);
        if (siapLokasi && siapFoto) {
            el('hint').textContent = '';
        } else if (!siapLokasi) {
            el('hint').textContent = 'Menunggu lokasi terbaca...';
        } else {
            el('hint').textContent = 'Ambil foto wajah dulu agar tombol aktif.';
        }
    }

    function bacaLokasi() {
        siapLokasi = false;
        perbaruiTombol();
        el('loc-title').textContent = 'Mencari lokasi...';
        el('loc-title').className = 'font-bold text-stone-500';

        if (!navigator.geolocation) {
            el('loc-detail').textContent = 'Browser ini tidak mendukung lokasi.';
            return;
        }

        navigator.geolocation.getCurrentPosition((pos) => {
            const { latitude, longitude, accuracy } = pos.coords;
            el('lat').value = latitude;
            el('lng').value = longitude;
            el('accuracy').value = accuracy;

            const jarak = haversine(latitude, longitude, SITE.lat, SITE.lng);
            const dalam = jarak <= SITE.radius;

            el('loc-title').textContent = dalam ? 'Lokasi: dalam area pabrik' : 'Lokasi: di luar area pabrik';
            el('loc-title').className = 'font-bold ' + (dalam ? 'text-green-700' : 'text-amber-700');
            el('loc-detail').textContent = jarak + ' m dari titik pabrik · akurasi ±' + Math.round(accuracy) + ' m'
                + (dalam ? '' : ' · tetap bisa absen, tapi akan ditandai');

            siapLokasi = true;
            perbaruiTombol();
        }, (err) => {
            el('loc-title').textContent = 'Lokasi tidak terbaca';
            el('loc-title').className = 'font-bold text-red-700';
            el('loc-detail').textContent = err.code === 1
                ? 'Akses lokasi ditolak. Izinkan lokasi untuk situs ini di pengaturan browser.'
                : 'Gagal membaca lokasi. Tekan Perbarui.';
        }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
    }

    el('photo').addEventListener('change', (e) => {
        const file = e.target.files[0];
        siapFoto = !!file;
        if (file) {
            el('photo-preview').src = URL.createObjectURL(file);
            el('photo-preview').classList.remove('hidden');
            el('photo-detail').textContent = 'Foto siap dikirim';
        }
        perbaruiTombol();
    });

    el('loc-refresh').addEventListener('click', bacaLokasi);

    el('absen-form').addEventListener('submit', () => {
        el('submit').disabled = true;
        el('submit').textContent = 'Mengirim...';
    });

    bacaLokasi();
</script>
@endif
</body>
</html>
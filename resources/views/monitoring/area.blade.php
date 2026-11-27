@extends('layouts.monitoring')

@section('title', 'Area absen')

@section('content')
@php
    $kelas = 'w-full rounded-xl border-2 border-stone-300 px-3 py-3 text-base';
@endphp

<h1 class="text-3xl font-extrabold">Area absen</h1>
<p class="text-sm text-stone-500">Titik pabrik dan radius yang dipakai untuk menentukan apakah operator absen di dalam area.</p>

@if (session('status'))
    <div class="mt-4 rounded-xl bg-green-100 p-3 text-sm font-semibold text-green-800">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="mt-4 rounded-xl bg-red-100 p-3 text-sm font-semibold text-red-800">
        @foreach ($errors->all() as $pesan)
            <p>{{ $pesan }}</p>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ route('monitoring.area.update') }}"
      class="mt-4 max-w-2xl space-y-4 rounded-2xl border border-stone-200 bg-white p-5">
    @csrf
    @method('PUT')

    <div>
        <label for="name" class="mb-1 block text-sm font-semibold">Nama area</label>
        <input id="name" name="name" type="text" maxlength="100" required
               value="{{ old('name', $site->name) }}" class="{{ $kelas }}">
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="latitude" class="mb-1 block text-sm font-semibold">Latitude</label>
            <input id="latitude" name="latitude" type="number" step="any" required
                   value="{{ old('latitude', $site->latitude) }}" class="{{ $kelas }}">
        </div>
        <div>
            <label for="longitude" class="mb-1 block text-sm font-semibold">Longitude</label>
            <input id="longitude" name="longitude" type="number" step="any" required
                   value="{{ old('longitude', $site->longitude) }}" class="{{ $kelas }}">
        </div>
    </div>

    <div>
        <button type="button" id="pakaiLokasi"
                class="rounded-xl border-2 border-[#1c1f24] px-4 py-2 text-sm font-bold">Pakai lokasi saya sekarang</button>
        <a id="lihatPeta" href="#" target="_blank" rel="noopener"
           class="ml-2 text-sm font-bold underline">Lihat titik di peta</a>
        <p id="pesanLokasi" class="mt-2 text-xs text-stone-500"></p>
    </div>

    <div>
        <label for="radius_m" class="mb-1 block text-sm font-semibold">Radius area (meter)</label>
        <input id="radius_m" name="radius_m" type="number" min="20" max="2000" required
               value="{{ old('radius_m', $site->radius_m) }}" class="{{ $kelas }}">
        <p class="mt-1 text-xs text-stone-500">
            Absen dengan jarak lebih dari radius ini ditandai "di luar area". Perubahan hanya berlaku untuk absen berikutnya,
            absen yang sudah tercatat tidak ikut berubah.
        </p>
    </div>

    <button class="rounded-xl bg-[#f5b800] px-5 py-3 font-extrabold">Simpan area</button>
</form>

<script>
    const lat = document.getElementById('latitude');
    const lng = document.getElementById('longitude');
    const pesan = document.getElementById('pesanLokasi');
    const peta = document.getElementById('lihatPeta');

    function perbaruiPeta() {
        const a = parseFloat(lat.value);
        const b = parseFloat(lng.value);
        peta.href = (isNaN(a) || isNaN(b)) ? '#' : 'https://www.google.com/maps?q=' + a + ',' + b;
    }

    lat.addEventListener('input', perbaruiPeta);
    lng.addEventListener('input', perbaruiPeta);
    perbaruiPeta();

    document.getElementById('pakaiLokasi').addEventListener('click', () => {
        if (!navigator.geolocation) {
            pesan.textContent = 'Browser ini tidak mendukung pembacaan lokasi.';
            return;
        }

        pesan.textContent = 'Membaca lokasi...';

        navigator.geolocation.getCurrentPosition(
            (pos) => {
                lat.value = pos.coords.latitude.toFixed(7);
                lng.value = pos.coords.longitude.toFixed(7);
                perbaruiPeta();
                pesan.textContent = 'Lokasi terbaca (akurasi sekitar ' + Math.round(pos.coords.accuracy)
                    + ' m). Periksa dulu, lalu klik Simpan area.';
            },
            (err) => {
                pesan.textContent = 'Gagal membaca lokasi: ' + err.message
                    + '. Izinkan akses lokasi di browser, atau isi koordinat manual.';
            },
            { enableHighAccuracy: true, timeout: 15000 }
        );
    });
</script>
@endsection
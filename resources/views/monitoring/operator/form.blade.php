@extends('layouts.monitoring')

@section('title', $operator ? 'Ubah operator' : 'Tambah operator')

@section('content')
@php
    $aksi = $operator
        ? route('monitoring.operator.update', $operator)
        : route('monitoring.operator.store');
    $kelas = 'w-full rounded-xl border-2 border-stone-300 px-3 py-3 text-base';
@endphp

<a href="{{ route('monitoring.operator.index') }}" class="text-sm font-bold">‹ Kembali ke data operator</a>

<h1 class="mt-2 text-3xl font-extrabold">{{ $operator ? 'Ubah operator' : 'Tambah operator' }}</h1>
<p class="text-sm text-stone-500">
    {{ $operator ? 'Ubah data operator. Riwayat absensi yang sudah ada tidak berubah.' : 'Akun baru langsung bisa dipakai untuk masuk.' }}
</p>

@if ($errors->any())
    <div class="mt-4 rounded-xl bg-red-100 p-3 text-sm font-semibold text-red-800">
        @foreach ($errors->all() as $pesan)
            <p>{{ $pesan }}</p>
        @endforeach
    </div>
@endif

<form method="POST" action="{{ $aksi }}" class="mt-4 max-w-2xl space-y-4 rounded-2xl border border-stone-200 bg-white p-5">
    @csrf
    @if ($operator)
        @method('PUT')
    @endif

    <div>
        <label for="name" class="mb-1 block text-sm font-semibold">Nama operator</label>
        <input id="name" name="name" type="text" maxlength="100" required
               value="{{ old('name', $operator?->name) }}" class="{{ $kelas }}">
    </div>

    <div>
        <label for="username" class="mb-1 block text-sm font-semibold">ID pengguna (untuk masuk)</label>
        <input id="username" name="username" type="text" maxlength="30" required autocomplete="off"
               value="{{ old('username', $operator?->username) }}" class="{{ $kelas }}">
        <p class="mt-1 text-xs text-stone-500">Huruf, angka, titik, strip, atau garis bawah. Contoh: operator16</p>
    </div>

    <div>
        <label for="password" class="mb-1 block text-sm font-semibold">
            {{ $operator ? 'Kata sandi baru (opsional)' : 'Kata sandi' }}
        </label>
        <input id="password" name="password" type="text" minlength="6" maxlength="100" autocomplete="off"
               @if (! $operator) required value="{{ old('password', 'password') }}" @endif
               placeholder="{{ $operator ? 'Kosongkan jika tidak diganti' : '' }}" class="{{ $kelas }}">
        <p class="mt-1 text-xs text-stone-500">
            {{ $operator ? 'Isi hanya kalau operator lupa kata sandi. Minimal 6 karakter.' : 'Minimal 6 karakter. Sarankan operator menggantinya setelah masuk.' }}
        </p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="shift_id" class="mb-1 block text-sm font-semibold">Shift</label>
            <select id="shift_id" name="shift_id" required class="{{ $kelas }}">
                <option value="">Pilih shift</option>
                @foreach ($shifts as $s)
                    <option value="{{ $s->id }}" @selected((string) old('shift_id', $operator?->shift_id) === (string) $s->id)>
                        {{ $s->name }} ({{ substr($s->start_time, 0, 5) }}–{{ substr($s->end_time, 0, 5) }})
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="work_system_id" class="mb-1 block text-sm font-semibold">Sistem kerja</label>
            <select id="work_system_id" name="work_system_id" required class="{{ $kelas }}">
                <option value="">Pilih sistem kerja</option>
                @foreach ($sistem as $w)
                    <option value="{{ $w->id }}" @selected((string) old('work_system_id', $operator?->work_system_id) === (string) $w->id)>
                        {{ $w->name }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>

    <label class="flex cursor-pointer items-center gap-3">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" class="h-5 w-5"
               @checked(old('is_active', $operator?->is_active ?? true))>
        <span class="text-sm font-semibold">Akun aktif (bisa masuk ke aplikasi)</span>
    </label>

    <div class="flex flex-wrap gap-3 pt-2">
        <button class="rounded-xl bg-[#f5b800] px-5 py-3 font-extrabold">{{ $operator ? 'Simpan perubahan' : 'Tambah operator' }}</button>
        <a href="{{ route('monitoring.operator.index') }}" class="rounded-xl border-2 border-[#1c1f24] px-5 py-3 font-bold">Batal</a>
    </div>
</form>
@endsection
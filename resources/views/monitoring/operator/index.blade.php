@extends('layouts.monitoring')

@section('title', 'Data operator')

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-3xl font-extrabold">Data operator</h1>
        <p class="text-sm text-stone-500">Kelola akun operator, shift, dan sistem kerjanya.</p>
    </div>
    <a href="{{ route('monitoring.operator.create') }}" class="rounded-xl bg-[#f5b800] px-5 py-3 font-extrabold">+ Tambah operator</a>
</div>

@if (session('status'))
    <div class="mt-4 rounded-xl bg-green-100 p-3 text-sm font-semibold text-green-800">{{ session('status') }}</div>
@endif

<div class="mt-4 grid grid-cols-3 gap-3">
    <div class="rounded-2xl border border-stone-200 bg-white p-4">
        <p class="text-sm text-stone-600">Total operator</p>
        <p class="text-4xl font-extrabold">{{ $total }}</p>
    </div>
    <div class="rounded-2xl border border-stone-200 bg-white p-4">
        <p class="text-sm text-stone-600">Aktif</p>
        <p class="text-4xl font-extrabold text-green-700">{{ $aktif }}</p>
    </div>
    <div class="rounded-2xl border border-stone-200 bg-white p-4">
        <p class="text-sm text-stone-600">Nonaktif</p>
        <p class="text-4xl font-extrabold text-stone-500">{{ $nonaktif }}</p>
    </div>
</div>

<section class="mt-4 rounded-2xl border border-stone-200 bg-white p-5">
    <form method="GET" action="{{ route('monitoring.operator.index') }}" class="flex flex-wrap items-center gap-2">
        <input type="text" name="cari" value="{{ $cari }}" placeholder="Cari nama atau ID pengguna"
               class="w-full rounded-xl border-2 border-stone-300 px-3 py-2 text-sm sm:w-72">

        <select name="status" class="rounded-xl border-2 border-stone-300 px-3 py-2 text-sm">
            <option value="">Semua status</option>
            <option value="aktif" @selected($status === 'aktif')>Aktif</option>
            <option value="nonaktif" @selected($status === 'nonaktif')>Nonaktif</option>
        </select>

        <button class="rounded-xl border-2 border-[#1c1f24] px-4 py-2 text-sm font-bold">Cari</button>

        @if ($cari !== '' || $status)
            <a href="{{ route('monitoring.operator.index') }}" class="text-sm font-bold underline">Reset</a>
        @endif
    </form>

    <div class="mt-4 overflow-x-auto rounded-xl border border-stone-200">
        <table class="w-full text-left text-sm">
            <thead class="bg-stone-50 text-xs uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">ID pengguna</th>
                    <th class="px-4 py-3">Shift</th>
                    <th class="px-4 py-3">Sistem kerja</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($operators as $o)
                    <tr class="border-t border-stone-100 align-middle">
                        <td class="px-4 py-3 font-bold">{{ $o->name }}</td>
                        <td class="px-4 py-3 text-stone-600">{{ $o->username }}</td>
                        <td class="px-4 py-3">
                            @if ($o->shift)
                                {{ $o->shift->name }}
                                <span class="text-xs text-stone-500">({{ substr($o->shift->start_time, 0, 5) }}–{{ substr($o->shift->end_time, 0, 5) }})</span>
                            @else
                                <span class="text-stone-400">Belum diatur</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $o->workSystem?->name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($o->is_active)
                                <span class="rounded-md bg-green-100 px-2 py-1 text-xs font-bold text-green-800">Aktif</span>
                            @else
                                <span class="rounded-md bg-stone-200 px-2 py-1 text-xs font-bold text-stone-700">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('monitoring.operator.edit', $o) }}"
                                   class="whitespace-nowrap rounded-lg border-2 border-[#1c1f24] px-3 py-1.5 text-xs font-bold">Ubah</a>

                                <form method="POST" action="{{ route('monitoring.operator.toggle', $o) }}"
                                      @if ($o->is_active) onsubmit="return confirm('Nonaktifkan {{ e($o->name) }}? Operator tidak bisa masuk lagi, riwayat absensinya tetap tersimpan.')" @endif>
                                    @csrf
                                    @method('PATCH')
                                    <button class="whitespace-nowrap rounded-lg border-2 px-3 py-1.5 text-xs font-bold {{ $o->is_active ? 'border-red-300 text-red-700' : 'border-green-300 text-green-700' }}">
                                        {{ $o->is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-stone-500">Tidak ada operator yang cocok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
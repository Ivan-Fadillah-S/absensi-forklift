<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Timesheet - Absensi Forklift</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f4f3ee] pb-24 text-[#1c1f24]">
@php
    $jam = fn ($t) => str_replace(':', '.', substr($t, 0, 5));
    $durasi = fn ($m) => \App\Support\Fmt::durasi($m);
    $sudahMasuk = (bool) $attendance?->check_in_at;
    $tidakMasuk = $attendance && $attendance->status !== 'hadir' && ! $attendance->check_in_at;
@endphp

<div class="mx-auto min-h-screen max-w-md">

    <header class="bg-[#1c1f24] px-5 pb-5 pt-6 text-white">
        <h1 class="text-2xl font-extrabold">Timesheet hari ini</h1>
        <p class="mt-1 text-sm text-gray-300">
            {{ $workDate->locale('id')->translatedFormat('l, j M Y') }}@if ($user->shift) · {{ $user->shift->name }}@endif
        </p>
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

        <section class="flex items-end justify-between rounded-2xl border border-stone-200 bg-white p-4">
            <div>
                <p class="text-sm text-stone-500">Total tercatat</p>
                <p class="text-3xl font-extrabold">{{ $durasi($totalMenit) }}</p>
            </div>
            <p class="text-sm text-stone-500">{{ $entries->count() }} catatan</p>
        </section>

        <h2 class="font-bold">Catatan kerja</h2>

        @forelse ($entries as $e)
            <div class="flex items-center justify-between gap-3 rounded-2xl border border-stone-200 bg-white p-4">
                <div>
                    <p class="font-bold">{{ $e->job_type }}</p>
                    <p class="text-sm text-stone-500">{{ $jam($e->start_time) }} – {{ $jam($e->end_time) }} · {{ $durasi($e->durationMinutes()) }}</p>
                    @if ($e->note)
                        <p class="text-sm text-stone-500">{{ $e->note }}</p>
                    @endif
                </div>
                <form method="POST" action="{{ route('operator.timesheet.destroy', $e) }}"
                      onsubmit="return confirm('Hapus catatan ini?')">
                    @csrf
                    @method('DELETE')
                    <button class="text-sm font-bold text-red-700">Hapus</button>
                </form>
            </div>
        @empty
            <p class="text-sm text-stone-500">Belum ada catatan kerja hari ini.</p>
        @endforelse

        <form method="POST" action="{{ route('operator.timesheet.store') }}"
              class="space-y-4 rounded-2xl border-2 border-[#1c1f24] bg-white p-4">
            @csrf
            <h2 class="font-bold">Tambah catatan</h2>

            <fieldset>
                <legend class="mb-2 text-sm font-semibold">Jenis pekerjaan</legend>
                <div class="flex flex-wrap gap-2">
                    @foreach ($jenis as $j)
                        <label class="cursor-pointer">
                            <input type="radio" name="job_type" value="{{ $j }}" class="peer sr-only"
                                   @checked(old('job_type') === $j) required>
                            <span class="block rounded-full border-2 border-stone-300 bg-white px-4 py-2 text-sm font-bold peer-checked:border-[#1c1f24] peer-checked:bg-[#1c1f24] peer-checked:text-white">{{ $j }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="start_time" class="mb-1 block text-sm font-semibold">Mulai</label>
                    <input id="start_time" name="start_time" type="time" value="{{ old('start_time') }}" required
                           class="w-full rounded-xl border border-stone-300 bg-white px-3 py-3 text-base">
                </div>
                <div>
                    <label for="end_time" class="mb-1 block text-sm font-semibold">Selesai</label>
                    <input id="end_time" name="end_time" type="time" value="{{ old('end_time') }}" required
                           class="w-full rounded-xl border border-stone-300 bg-white px-3 py-3 text-base">
                </div>
            </div>

            <div>
                <label for="note" class="mb-1 block text-sm font-semibold">
                    Catatan <span class="font-normal text-stone-500">(wajib untuk "Lainnya")</span>
                </label>
                <input id="note" name="note" type="text" maxlength="255" value="{{ old('note') }}"
                       class="w-full rounded-xl border border-stone-300 bg-white px-3 py-3 text-base">
            </div>

            <button class="w-full rounded-xl bg-[#1c1f24] py-3 font-bold text-white">Simpan catatan</button>
        </form>

        <p class="text-sm text-stone-500">Monitoring dapat menyesuaikan catatan ini bila ada koreksi.</p>

        {{-- Cuti / izin / sakit (hanya untuk hari ini) --}}
        @if ($tidakMasuk)
            <section class="space-y-3 rounded-2xl border border-amber-300 bg-amber-50 p-4">
                <h2 class="font-bold">Hari ini tercatat: {{ ucfirst($attendance->status) }}</h2>
                <p class="text-sm">{{ $attendance->note }}</p>
                <form method="POST" action="{{ route('operator.timesheet.status.clear') }}">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-xl border-2 border-[#1c1f24] px-4 py-2 text-sm font-bold">Batalkan keterangan</button>
                </form>
            </section>
        @elseif (! $sudahMasuk)
            <form method="POST" action="{{ route('operator.timesheet.status') }}"
                  class="space-y-3 rounded-2xl border border-stone-200 bg-white p-4">
                @csrf
                <h2 class="font-bold">Tidak masuk hari ini?</h2>
                <div class="flex gap-2">
                    @foreach (['cuti' => 'Cuti', 'izin' => 'Izin', 'sakit' => 'Sakit'] as $nilai => $label)
                        <label class="flex-1 cursor-pointer text-center">
                            <input type="radio" name="status" value="{{ $nilai }}" class="peer sr-only" required>
                            <span class="block rounded-xl border-2 border-stone-300 bg-white py-2 text-sm font-bold peer-checked:border-[#1c1f24] peer-checked:bg-[#1c1f24] peer-checked:text-white">{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
                <input name="note" type="text" maxlength="255" placeholder="Alasan singkat" required
                       class="w-full rounded-xl border border-stone-300 bg-white px-3 py-3 text-base">
                <button class="w-full rounded-xl border-2 border-[#1c1f24] py-3 font-bold">Simpan keterangan</button>
            </form>
        @endif
    </main>

    @include('partials.operator-nav', ['aktif' => 'timesheet'])
</div>
</body>
</html>
@php
    $tab = [
        'absen'     => ['Absen', route('operator.absen')],
        'timesheet' => ['Timesheet', route('operator.timesheet')],
        'riwayat'   => ['Riwayat', route('operator.riwayat')],
    ];
@endphp
<nav class="fixed bottom-0 left-1/2 flex w-full max-w-md -translate-x-1/2 border-t border-stone-200 bg-white text-center text-sm font-bold">
    @foreach ($tab as $kunci => [$label, $url])
        <a href="{{ $url }}"
            class="flex-1 border-t-4 py-4 {{ $aktif === $kunci ? 'border-[#f5b800] text-[#1c1f24]' : 'border-transparent text-stone-400' }}">{{ $label }}</a>
    @endforeach
</nav>
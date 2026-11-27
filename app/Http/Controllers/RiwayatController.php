<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RiwayatController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'bulan'  => ['nullable', 'date_format:Y-m'],
            'filter' => ['nullable', 'in:dikoreksi'],
        ]);

        $user = $request->user()->load(['shift', 'workSystem']);

        // Periode dinamai dari bulan AKHIR (tanggal 25).
        // Contoh: 2026-11 berarti 26 Okt - 25 Nov.
        $periodeSekarang = now()->startOfMonth();
        if (now()->day >= 26) {
            $periodeSekarang->addMonth();
        }

        $akhirBulan = $request->filled('bulan')
            ? Carbon::createFromFormat('Y-m-d', $request->query('bulan') . '-01')->startOfDay()
            : $periodeSekarang->copy();

        $mulai = $akhirBulan->copy()->subMonthNoOverflow()->day(26);
        $akhir = $akhirBulan->copy()->day(25);

        $semua = Attendance::where('user_id', $user->id)
            ->whereBetween('work_date', [$mulai->toDateString(), $akhir->toDateString()])
            ->orderByDesc('work_date')
            ->get();

        // Pakai data user yang sudah dimuat, supaya tidak query berulang
        $semua->each(fn ($a) => $a->setRelation('user', $user));

        $dikoreksi = $semua->filter(fn ($a) => $a->isCorrected());
        $filter = $request->query('filter');

        return view('operator.riwayat', [
            'daftar'       => $filter === 'dikoreksi' ? $dikoreksi : $semua,
            'semua'        => $semua,
            'dikoreksi'    => $dikoreksi,
            'filter'       => $filter,
            'hariHadir'    => $semua->filter(fn ($a) => $a->status === 'hadir' && ($a->check_in_at || $a->check_out_at))->count(),
            'lemburMenit'  => $semua->sum(fn ($a) => $a->overtimeMinutes()),
            'bulan'        => $akhirBulan->format('Y-m'),
            'prev'         => $akhirBulan->copy()->subMonthNoOverflow()->format('Y-m'),
            'next'         => $akhirBulan->copy()->addMonthNoOverflow()->format('Y-m'),
            'bisaNext'     => $akhirBulan->lt($periodeSekarang),
            'labelPeriode' => $mulai->locale('id')->translatedFormat('j M') . ' – ' . $akhir->locale('id')->translatedFormat('j M Y'),
        ]);
    }
}
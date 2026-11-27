<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Support\Rekap;
use App\Support\RekapExcel;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RekapController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'bulan'  => ['nullable', 'date_format:Y-m'],
            'filter' => ['nullable', 'in:lupa'],
        ]);

        [$akhirBulan, $mulai, $akhir] = Rekap::periode($request->query('bulan'));
        [$bulanSekarang] = Rekap::periode(null);

        $baris = collect(Rekap::bangun($mulai, $akhir));
        $filter = $request->query('filter');

        return view('monitoring.rekap', [
            'baris'        => $filter === 'lupa' ? $baris->filter(fn ($r) => $r['belum'] > 0)->values() : $baris,
            'total'        => $baris->count(),
            'belumFinal'   => $baris->filter(fn ($r) => $r['belum'] > 0)->count(),
            'filter'       => $filter,
            'bulan'        => $akhirBulan->format('Y-m'),
            'prev'         => $akhirBulan->copy()->subMonthNoOverflow()->format('Y-m'),
            'next'         => $akhirBulan->copy()->addMonthNoOverflow()->format('Y-m'),
            'bisaNext'     => $akhirBulan->lt($bulanSekarang),
            'labelPeriode' => $mulai->copy()->locale('id')->translatedFormat('j M') . ' – ' . $akhir->copy()->locale('id')->translatedFormat('j M Y'),
        ]);
    }

    public function harian(Request $request, User $operator)
    {
        abort_unless($operator->role === 'operator', 404);

        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);

        [$akhirBulan, $mulai, $akhir] = Rekap::periode($request->query('bulan'));
        $operator->load(['shift', 'workSystem']);

        $absen = Attendance::where('user_id', $operator->id)
            ->whereBetween('work_date', [$mulai->toDateString(), $akhir->toDateString()])
            ->get()
            ->each(fn ($a) => $a->setRelation('user', $operator))
            ->keyBy(fn ($a) => $a->work_date->toDateString());

        $hari = [];
        foreach (CarbonPeriod::create($mulai, $akhir) as $tgl) {
            $a = $absen->get($tgl->toDateString());

            $hari[] = [
                'tgl'    => $tgl,
                'a'      => $a,
                'detail' => $a ? Rekap::detailHari($a) : null,
            ];
        }

        return view('monitoring.rekap-harian', [
            'operator'     => $operator,
            'hari'         => $hari,
            'bulan'        => $akhirBulan->format('Y-m'),
            'prev'         => $akhirBulan->copy()->subMonthNoOverflow()->format('Y-m'),
            'next'         => $akhirBulan->copy()->addMonthNoOverflow()->format('Y-m'),
            'bisaNext'     => $akhirBulan->lt(Rekap::periode(null)[0]),
            'labelPeriode' => $mulai->copy()->locale('id')->translatedFormat('j M') . ' – ' . $akhir->copy()->locale('id')->translatedFormat('j M Y'),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);

        [$akhirBulan, $mulai, $akhir] = Rekap::periode($request->query('bulan'));

        $buku = RekapExcel::buat(
            Rekap::bangun($mulai, $akhir),
            $mulai,
            $akhir,
            $request->user()->site?->name ?? 'Site'
        );

        return response()->streamDownload(function () use ($buku) {
            (new Xlsx($buku))->save('php://output');
        }, 'rekap-absensi-' . $akhirBulan->format('Y-m') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
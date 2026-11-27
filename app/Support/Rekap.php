<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

class Rekap
{
    /**
     * Periode dinamai dari bulan AKHIR (format Y-m).
     * Contoh: "2026-11" berarti 26 Okt - 25 Nov 2026.
     * Hasilnya [bulan akhir, tanggal mulai, tanggal akhir].
     */
    public static function periode(?string $bulan): array
    {
        if ($bulan) {
            $akhirBulan = Carbon::createFromFormat('Y-m-d', $bulan . '-01')->startOfDay();
        } else {
            $akhirBulan = now()->startOfMonth();

            if (now()->day >= 26) {
                $akhirBulan->addMonth();
            }
        }

        $mulai = $akhirBulan->copy()->subMonthNoOverflow()->day(26);
        $akhir = $akhirBulan->copy()->day(25);

        return [$akhirBulan, $mulai, $akhir];
    }

    /**
     * Rekap semua operator aktif pada rentang tanggal.
     */
    public static function bangun(Carbon $mulai, Carbon $akhir): array
    {
        $operators = User::where('role', 'operator')
            ->where('is_active', true)
            ->with(['shift', 'workSystem'])
            ->orderBy('username')
            ->get();

        $absen = Attendance::whereBetween('work_date', [$mulai->toDateString(), $akhir->toDateString()])
            ->orderBy('work_date')
            ->get()
            ->groupBy('user_id');

        $sekarang = now();
        $hasil = [];

        foreach ($operators as $op) {
            $daftar = $absen->get($op->id, collect());
            $daftar->each(fn ($a) => $a->setRelation('user', $op));

            $jamPerHari = $op->workSystem?->normal_hours ?? 8;

            $r = [
                'user'         => $op,
                'jamPerHari'   => $jamPerHari,
                'hadir'        => 0,
                'lemburMenit'  => 0,
                'cuti'         => 0,
                'izin'         => 0,
                'sakit'        => 0,
                'dikoreksi'    => 0,
                'belum'        => 0,
                'tanggalBelum' => [],
                'harian'       => $daftar,
            ];

            foreach ($daftar as $a) {
                $adaMasuk = (bool) $a->check_in_at;
                $adaPulang = (bool) $a->check_out_at;

                // Cuti / izin / sakit (tanpa jam absen)
                if (in_array($a->status, ['cuti', 'izin', 'sakit'], true) && ! $adaMasuk && ! $adaPulang) {
                    $r[$a->status]++;
                    continue;
                }

                if (! $adaMasuk && ! $adaPulang) {
                    continue;
                }

                $r['hadir']++;
                $r['lemburMenit'] += $a->overtimeMinutes();

                if ($a->isCorrected()) {
                    $r['dikoreksi']++;
                }

                // Tinggal satu jam: baru disebut lupa setelah shift berakhir
                if ($adaMasuk xor $adaPulang) {
                    $jendela = DailyStatus::shiftWindow($op, $a->work_date);

                    if (! $jendela || $sekarang->gte($jendela[1])) {
                        $r['belum']++;
                        $r['tanggalBelum'][] = $a->work_date->toDateString();
                    }
                }
            }

            $r['jamNormal'] = $r['hadir'] * $jamPerHari;
            $r['lemburJam'] = round($r['lemburMenit'] / 60, 2);
            $r['totalJam'] = $r['jamNormal'] + $r['lemburJam'];

            $hasil[] = $r;
        }

        return $hasil;
    }

    /**
     * Rincian satu hari absen, dipakai halaman Harian dan Excel.
     */
    public static function detailHari(Attendance $a): array
    {
        $lembur = $a->overtimeMinutes();
        $akhirShift = $a->shiftEnd();
        $adaJam = $a->check_in_at || $a->check_out_at;

        if (! $adaJam && $a->status !== 'hadir') {
            $ket = ucfirst($a->status) . ($a->note ? ' · ' . $a->note : '');
        } elseif ($adaJam && ! ($a->check_in_at && $a->check_out_at)) {
            $ket = $a->check_in_at ? 'Lupa absen pulang' : 'Lupa absen masuk';
        } else {
            $ket = $a->statusLabel();
        }

        return [
            'masuk'         => $a->check_in_at?->format('H.i'),
            'pulang'        => $a->check_out_at?->format('H.i'),
            'lemburMenit'   => $lembur,
            'lemburMulai'   => $lembur > 0 && $akhirShift ? $akhirShift->format('H.i') : null,
            'lemburSelesai' => $lembur > 0 && $akhirShift ? $akhirShift->copy()->addMinutes($lembur)->format('H.i') : null,
            'keterangan'    => $ket,
            'dikoreksi'     => $a->isCorrected(),
            'masukOleh'     => $a->check_in_source,
            'pulangOleh'    => $a->check_out_source,
        ];
    }
}
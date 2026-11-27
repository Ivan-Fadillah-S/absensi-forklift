<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;

class DailyStatus
{
    /**
     * Jendela waktu shift operator pada tanggal tertentu: [mulai, selesai].
     */
    public static function shiftWindow(User $user, Carbon $tanggal): ?array
    {
        $shift = $user->shift;

        if (! $shift) {
            return null;
        }

        $tgl = $tanggal->toDateString();
        $mulai = Carbon::parse("{$tgl} {$shift->start_time}");
        $akhir = Carbon::parse("{$tgl} {$shift->end_time}");

        if ($akhir->lte($mulai)) {
            $akhir->addDay(); // shift yang melewati tengah malam
        }

        return [$mulai, $akhir];
    }

    /**
     * Hasilnya [kode, label].
     * Kode: lengkap, lupa_masuk, lupa_pulang, belum, izin, bekerja, menunggu
     */
    public static function resolve(User $user, ?Attendance $a, Carbon $tanggal): array
    {
        $sekarang = now();

        // Cuti / izin / sakit
        if ($a && in_array($a->status, ['cuti', 'izin', 'sakit'], true) && ! $a->check_in_at) {
            return ['izin', ucfirst($a->status)];
        }

        $masuk = $a?->check_in_at;
        $pulang = $a?->check_out_at;
        $jendela = self::shiftWindow($user, $tanggal);

        if ($masuk && $pulang) {
            return ['lengkap', 'Lengkap'];
        }

        if ($masuk) {
            // Masih dalam jam shift: belum bisa disebut lupa
            if ($jendela && $sekarang->lt($jendela[1])) {
                return ['bekerja', 'Sedang bekerja'];
            }

            return ['lupa_pulang', 'Lupa absen pulang'];
        }

        if ($pulang) {
            return ['lupa_masuk', 'Lupa absen masuk'];
        }

        // Belum ada data sama sekali
        if ($jendela && $sekarang->lt($jendela[0]->copy()->subHour())) {
            return ['menunggu', 'Belum mulai'];
        }

        return ['belum', 'Belum absen'];
    }
}
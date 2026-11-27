<?php

namespace App\Support;

class Fmt
{
    /**
     * Ubah menit menjadi teks, contoh: 225 -> "3 j 45 m".
     */
    public static function durasi(int $menit): string
    {
        $jam = intdiv($menit, 60);
        $sisa = $menit % 60;

        if ($jam === 0) {
            return $sisa . ' m';
        }

        return $sisa === 0 ? "{$jam} j" : "{$jam} j {$sisa} m";
    }

    /**
     * Ubah meter menjadi teks, contoh: 42 -> "42 m", 1800 -> "1,8 km".
     */
    public static function jarak(?int $meter): string
    {
        if ($meter === null) {
            return '—';
        }

        if ($meter < 1000) {
            return $meter . ' m';
        }

        return number_format($meter / 1000, 1, ',', '.') . ' km';
    }
}
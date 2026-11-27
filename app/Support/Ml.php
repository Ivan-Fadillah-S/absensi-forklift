<?php

namespace App\Support;

class Ml
{
    /** Ambang baku modified z-score (Iglewicz dan Hoaglin, 1993). */
    public const AMBANG = 3.5;

    public static function median(array $x): float
    {
        sort($x);
        $n = count($x);
        $tengah = intdiv($n, 2);

        return $n % 2 ? (float) $x[$tengah] : ($x[$tengah - 1] + $x[$tengah]) / 2;
    }

    /**
     * "Melatih" model: mempelajari pola normal (median dan skala sebaran) dari data.
     * Mengembalikan null bila data terlalu sedikit untuk dipelajari.
     */
    public static function latih(array $nilai, int $minimal = 8): ?array
    {
        $nilai = array_values($nilai);

        if (count($nilai) < $minimal) {
            return null;
        }

        $median = self::median($nilai);
        $selisih = array_map(fn ($v) => abs($v - $median), $nilai);
        $mad = self::median($selisih);

        if ($mad > 0) {
            $skala = $mad / 0.6745;
        } else {
            $rata = array_sum($selisih) / count($selisih);

            if ($rata == 0) {
                return null;
            }

            $skala = 1.253314 * $rata;
        }

        return ['median' => $median, 'skala' => $skala];
    }

    /**
     * Skor anomali (modified z-score). Semakin jauh dari 0, semakin tidak biasa.
     */
    public static function skor(float $nilai, array $model): float
    {
        return ($nilai - $model['median']) / $model['skala'];
    }
}
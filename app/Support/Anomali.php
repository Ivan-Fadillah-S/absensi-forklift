<?php

namespace App\Support;

use App\Models\Attendance;
use Carbon\Carbon;

class Anomali
{
    /**
     * Cari absen yang perlu diperiksa pada rentang tanggal.
     * Dua lapis: (1) aturan tetap, (2) model statistik yang belajar dari data.
     * Ini penanda untuk diperiksa, bukan bukti kecurangan.
     */
    public static function cari(Carbon $mulai, Carbon $akhir): array
    {
        $absen = Attendance::with('user.shift', 'user.workSystem')
            ->whereBetween('work_date', [$mulai->toDateString(), $akhir->toDateString()])
            ->whereHas('user', fn ($q) => $q->where('role', 'operator'))
            ->orderBy('work_date')
            ->get();

        $temuan = [];
        $ditandai = []; // "idAbsen:fitur" yang sudah ditangkap aturan, supaya tidak ganda

        $tambah = function (Attendance $a, string $tingkat, string $aturan, string $pesan, ?string $fitur = null)
            use (&$temuan, &$ditandai) {
            $temuan[] = [
                'tingkat'  => $tingkat,
                'aturan'   => $aturan,
                'pesan'    => $pesan,
                'operator' => $a->user->name,
                'user'     => $a->user,
                'tanggal'  => $a->work_date->toDateString(),
            ];

            if ($fitur) {
                $ditandai["{$a->id}:{$fitur}"] = true;
            }
        };

        // ===== Lapis 1: aturan tetap =====
        foreach ($absen as $a) {
            $u = $a->user;

            foreach (['check_in' => 'masuk', 'check_out' => 'pulang'] as $p => $nama) {
                if ($a->{"{$p}_source"} !== 'operator') {
                    continue;
                }

                if ($a->{"{$p}_outside_area"}) {
                    $tambah($a, 'sedang', 'Di luar area',
                        "Absen {$nama} di luar area pabrik (" . Fmt::jarak($a->{"{$p}_distance_m"}) . ' dari titik pabrik).',
                        $p === 'check_in' ? 'jarak' : null);
                }

                $akurasi = $a->{"{$p}_accuracy_m"};
                if ($akurasi !== null && $akurasi > 100) {
                    $tambah($a, 'sedang', 'Akurasi GPS rendah',
                        "Akurasi GPS absen {$nama} hanya ±{$akurasi} m, lokasi kurang dapat dipercaya.",
                        $p === 'check_in' ? 'akurasi' : null);
                }
            }

            if ($a->check_in_at && $a->check_in_source === 'operator') {
                $jendela = DailyStatus::shiftWindow($u, $a->work_date);

                if ($jendela) {
                    $selisih = intdiv($a->check_in_at->timestamp - $jendela[0]->timestamp, 60);

                    if (abs($selisih) > 120) {
                        $tambah($a, 'sedang', 'Jam jauh dari shift',
                            'Absen masuk ' . $a->check_in_at->format('H.i') . ' berselisih ' . Fmt::durasi(abs($selisih))
                            . ' dari jadwal shift (' . $jendela[0]->format('H.i') . ').', 'masuk');
                    }
                }
            }

            if ($a->check_in_at && $a->check_out_at) {
                $menit = intdiv($a->check_out_at->timestamp - $a->check_in_at->timestamp, 60);
                $batas = ($u->workSystem?->max_total_hours ?? 12) * 60;

                if ($menit > $batas) {
                    $tambah($a, 'tinggi', 'Durasi melebihi batas',
                        'Durasi kerja ' . Fmt::durasi($menit) . ' melebihi batas ' . intdiv($batas, 60) . ' jam per hari.', 'durasi');
                } elseif ($menit < 240) {
                    $tambah($a, 'sedang', 'Durasi terlalu singkat',
                        'Durasi kerja hanya ' . Fmt::durasi($menit) . '.', 'durasi');
                }
            }

            if ($a->check_in_lat !== null && $a->check_out_lat !== null) {
                $m = Geo::distanceMeters(
                    (float) $a->check_in_lat, (float) $a->check_in_lng,
                    (float) $a->check_out_lat, (float) $a->check_out_lng
                );

                if ($m > 1000) {
                    $tambah($a, 'sedang', 'Lokasi masuk dan pulang berjauhan',
                        'Lokasi absen masuk dan pulang berjarak ' . Fmt::jarak($m) . '.');
                }
            }
        }

        foreach ($absen->groupBy('user_id') as $daftar) {
            $kelompok = $daftar
                ->filter(fn ($a) => $a->check_in_lat !== null && $a->check_in_source === 'operator')
                ->groupBy(fn ($a) => round((float) $a->check_in_lat, 5) . ',' . round((float) $a->check_in_lng, 5));

            foreach ($kelompok as $sama) {
                if ($sama->count() >= 3) {
                    $tambah($sama->last(), 'tinggi', 'Koordinat identik',
                        "Koordinat absen masuk identik pada {$sama->count()} hari (sama sampai 5 angka desimal). "
                        . 'GPS asli biasanya bergeser tiap hari; pola ini khas lokasi palsu atau data yang disalin.');
                }
            }

            $dikoreksi = $daftar->filter(fn ($a) => $a->isCorrected())->count();
            if ($dikoreksi >= 3) {
                $tambah($daftar->last(), 'sedang', 'Sering dikoreksi',
                    "{$dikoreksi} hari pada periode ini dikoreksi oleh monitoring.");
            }
        }

        // ===== Lapis 2: model statistik (machine learning tak terawasi) =====
        $fitur = ['masuk' => [], 'durasi' => [], 'jarak' => [], 'akurasi' => []];

        foreach ($absen as $a) {
            $masukOperator = $a->check_in_at && $a->check_in_source === 'operator';

            if ($masukOperator) {
                $jendela = DailyStatus::shiftWindow($a->user, $a->work_date);

                if ($jendela) {
                    $fitur['masuk'][$a->id] = intdiv($a->check_in_at->timestamp - $jendela[0]->timestamp, 60);
                }
                if ($a->check_in_distance_m !== null) {
                    $fitur['jarak'][$a->id] = (float) $a->check_in_distance_m;
                }
                if ($a->check_in_accuracy_m !== null) {
                    $fitur['akurasi'][$a->id] = (float) $a->check_in_accuracy_m;
                }
            }

            if ($masukOperator && $a->check_out_at && $a->check_out_source === 'operator') {
                $fitur['durasi'][$a->id] = intdiv($a->check_out_at->timestamp - $a->check_in_at->timestamp, 60);
            }
        }

        $konfigurasi = [
            'masuk'   => ['sisi' => 'dua',   'judul' => 'jam masuk menyimpang',    'satuan' => 'menit dari awal shift'],
            'durasi'  => ['sisi' => 'bawah', 'judul' => 'durasi kerja menyimpang', 'satuan' => 'menit'],
            'jarak'   => ['sisi' => 'atas',  'judul' => 'jarak absen menyimpang',  'satuan' => 'm dari titik pabrik'],
            'akurasi' => ['sisi' => 'atas',  'judul' => 'akurasi GPS menyimpang',  'satuan' => 'm'],
        ];

        $dataAbsen = $absen->keyBy('id');

        foreach ($konfigurasi as $nama => $k) {
            $model = Ml::latih(array_values($fitur[$nama]));

            if (! $model) {
                continue; // data belum cukup untuk dipelajari
            }

            foreach ($fitur[$nama] as $id => $nilai) {
                if (isset($ditandai["{$id}:{$nama}"])) {
                    continue; // sudah tertangkap aturan tetap
                }

                $skor = Ml::skor($nilai, $model);

                $menyimpang = match ($k['sisi']) {
                    'atas'  => $skor > Ml::AMBANG,
                    'bawah' => $skor < -Ml::AMBANG,
                    default => abs($skor) > Ml::AMBANG,
                };

                if (! $menyimpang) {
                    continue;
                }

                $tambah($dataAbsen[$id], abs($skor) > 6 ? 'tinggi' : 'sedang', 'Model statistik: ' . $k['judul'],
                    "Nilai {$nilai} {$k['satuan']} jauh dari pola umum seluruh operator (median "
                    . round($model['median'], 1) . '). Skor anomali ' . number_format(abs($skor), 1, ',', '.') . '.');
            }
        }

        // Urutkan: tingkat tinggi dulu, lalu yang terbaru
        usort($temuan, fn ($x, $y) => [$x['tingkat'] === 'tinggi' ? 0 : 1, $y['tanggal']]
            <=> [$y['tingkat'] === 'tinggi' ? 0 : 1, $x['tanggal']]);

        return $temuan;
    }
}
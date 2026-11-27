<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoAttendanceSeeder extends Seeder
{
    /**
     * Data contoh Jumat 6 Nov 2026, mengikuti mockup dashboard.
     * Jalankan: php artisan db:seed --class=DemoAttendanceSeeder
     */
    public function run(): void
    {
        $tgl = '2026-11-06';
        $radius = Site::first()->radius_m;

        // Bersihkan absen tanggal ini supaya aman dijalankan berulang
        Attendance::whereDate('work_date', $tgl)->delete();

        // [no operator, jam masuk, jam pulang, jarak masuk (m), jarak pulang (m)]
        $data = [
            [1,  '06:58', '15:04', 42, 42],
            [2,  '07:02', '15:00', 18, 18],
            [3,  '07:00', null,    60, null],
            [4,  null,    '23:01', null, 35],
            [5,  '14:57', '23:05', 1800, 1800],
            [6,  '06:55', '17:10', 22, 22],
            [8,  '07:04', null,    51, null],
            [9,  '14:59', '23:02', 27, 27],
            [10, '15:01', '23:04', 30, 30],
            [11, '07:00', '15:03', 12, 12],
            [13, '06:57', '15:01', 48, 48],
            [14, '15:03', '23:00', 20, 20],
            // Operator 07, 12, 15: sengaja tanpa data (belum absen)
        ];

        foreach ($data as [$no, $masuk, $pulang, $jarakMasuk, $jarakPulang]) {
            $user = User::where('username', sprintf('operator%02d', $no))->firstOrFail();

            $baris = [
                'user_id'   => $user->id,
                'work_date' => $tgl,
                'status'    => 'hadir',
            ];

            if ($masuk) {
                $baris += [
                    'check_in_at'           => "{$tgl} {$masuk}:00",
                    'check_in_source'       => 'operator',
                    'check_in_distance_m'   => $jarakMasuk,
                    'check_in_outside_area' => $jarakMasuk > $radius,
                ];
            }

            if ($pulang) {
                $baris += [
                    'check_out_at'           => "{$tgl} {$pulang}:00",
                    'check_out_source'       => 'operator',
                    'check_out_distance_m'   => $jarakPulang,
                    'check_out_outside_area' => $jarakPulang > $radius,
                ];
            }

            Attendance::forceCreate($baris);
        }
    }
}
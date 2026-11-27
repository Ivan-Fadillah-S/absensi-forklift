<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Site;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Database\Seeder;

class DemoAnomaliSeeder extends Seeder
{
    /**
     * Data contoh 9-11 Nov 2026 dengan pola yang mencurigakan.
     * Jalankan: php artisan db:seed --class=DemoAnomaliSeeder
     */
    public function run(): void
    {
        $site = Site::first();
        $lat = (float) $site->latitude;
        $lng = (float) $site->longitude;
        $radius = $site->radius_m;

        // [username, tanggal, masuk, pulang, lat/lng/akurasi masuk, lat/lng/akurasi pulang]
        $data = [
            // Operator 09: koordinat identik tiga hari berturut-turut (pola lokasi palsu)
            ['operator09', '2026-11-09', '14:58', '23:01', [$lat, $lng, 5], [$lat, $lng, 5]],
            ['operator09', '2026-11-10', '15:01', '23:03', [$lat, $lng, 5], [$lat, $lng, 5]],
            ['operator09', '2026-11-11', '14:59', '23:00', [$lat, $lng, 5], [$lat, $lng, 5]],
            // Operator 10: absen jauh sebelum shift dan kerja lebih dari 12 jam
            ['operator10', '2026-11-09', '10:30', '23:00', [$lat + 0.0002, $lng, 12], [$lat + 0.0002, $lng, 12]],
            // Operator 13: pulang terlalu cepat
            ['operator13', '2026-11-10', '07:00', '10:15', [$lat + 0.0003, $lng, 10], [$lat + 0.0003, $lng, 10]],
            // Operator 14: akurasi GPS buruk
            ['operator14', '2026-11-11', '15:00', '23:00', [$lat + 0.0004, $lng, 850], [$lat + 0.0004, $lng, 600]],
            // Operator 11: pulang dari lokasi yang jauh
            ['operator11', '2026-11-09', '07:00', '15:00', [$lat, $lng + 0.0003, 8], [$lat + 0.02, $lng, 9]],

                        // Operator 02: masuk 80 menit setelah shift (di bawah batas aturan 120 menit, tetapi aneh secara statistik)
            ['operator02', '2026-11-10', '08:20', '16:20', [$lat + 0.0001, $lng, 9], [$lat + 0.0001, $lng, 9]],
            // Operator 06: pulang jauh lebih awal, 5 jam (di atas batas aturan 4 jam)
            ['operator06', '2026-11-11', '07:02', '12:05', [$lat + 0.0002, $lng + 0.0001, 7], [$lat + 0.0002, $lng + 0.0001, 7]],
        ];

        $users = User::whereIn('username', array_unique(array_column($data, 0)))->get()->keyBy('username');

        Attendance::whereIn('user_id', $users->pluck('id'))
            ->whereBetween('work_date', ['2026-11-09', '2026-11-11'])
            ->delete();

        foreach ($data as [$username, $tgl, $masuk, $pulang, $lokM, $lokP]) {
            $jarakM = Geo::distanceMeters($lokM[0], $lokM[1], $lat, $lng);
            $jarakP = Geo::distanceMeters($lokP[0], $lokP[1], $lat, $lng);

            Attendance::forceCreate([
                'user_id'                => $users[$username]->id,
                'work_date'              => $tgl,
                'status'                 => 'hadir',
                'check_in_at'            => "{$tgl} {$masuk}:00",
                'check_in_source'        => 'operator',
                'check_in_lat'           => $lokM[0],
                'check_in_lng'           => $lokM[1],
                'check_in_accuracy_m'    => $lokM[2],
                'check_in_distance_m'    => $jarakM,
                'check_in_outside_area'  => $jarakM > $radius,
                'check_out_at'           => "{$tgl} {$pulang}:00",
                'check_out_source'       => 'operator',
                'check_out_lat'          => $lokP[0],
                'check_out_lng'          => $lokP[1],
                'check_out_accuracy_m'   => $lokP[2],
                'check_out_distance_m'   => $jarakP,
                'check_out_outside_area' => $jarakP > $radius,
            ]);
        }
    }
}
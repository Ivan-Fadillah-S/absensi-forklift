<?php

namespace Database\Seeders;

use App\Models\Shift;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkSystem;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // DATA CONTOH. Nilai asli (titik pabrik, radius, sistem kerja)
        // masih menunggu dari klien, nanti diganti.
        $site = Site::forceCreate([
            'name' => 'Site Cikarang (contoh)',
            'latitude' => -6.3000000,
            'longitude' => 107.1500000,
            'radius_m' => 100,
        ]);

        $sistemA = WorkSystem::forceCreate([
            'name' => 'Sistem kerja A (8 jam)',
            'normal_hours' => 8,
            'max_total_hours' => 12,
        ]);

        $sistemB = WorkSystem::forceCreate([
            'name' => 'Sistem kerja B (7 jam)',
            'normal_hours' => 7,
            'max_total_hours' => 12,
        ]);

        $shift1 = Shift::forceCreate([
            'name' => 'Shift 1',
            'start_time' => '07:00',
            'end_time' => '15:00',
        ]);

        $shift2 = Shift::forceCreate([
            'name' => 'Shift 2',
            'start_time' => '15:00',
            'end_time' => '23:00',
        ]);

        // Akun monitoring (Pak Anwar)
        User::forceCreate([
            'name' => 'Anwar',
            'username' => 'anwar',
            'password' => 'password',
            'role' => 'monitoring',
            'site_id' => $site->id,
        ]);

        // 15 operator, pembagian shift mengikuti mockup
        $shift2Ids = [4, 5, 9, 10, 14, 15];
        $sistemBIds = [4, 5];

        for ($i = 1; $i <= 15; $i++) {
            $nomor = sprintf('%02d', $i);

            User::forceCreate([
                'name' => "Operator {$nomor}",
                'username' => "operator{$nomor}",
                'password' => 'password',
                'role' => 'operator',
                'site_id' => $site->id,
                'shift_id' => in_array($i, $shift2Ids) ? $shift2->id : $shift1->id,
                'work_system_id' => in_array($i, $sistemBIds) ? $sistemB->id : $sistemA->id,
            ]);
        }
    }
}
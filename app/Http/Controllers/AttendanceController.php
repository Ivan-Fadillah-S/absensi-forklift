<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\User;
use App\Support\Geo;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load(['site', 'shift', 'workSystem']);

        abort_unless($user->site, 422, 'Akun Anda belum punya area pabrik. Hubungi monitoring.');

        $attendance = Attendance::currentFor($user);

        $state = 'belum';
        if ($attendance?->check_in_at && $attendance->check_out_at) {
            $state = 'selesai';
        } elseif ($attendance?->check_in_at) {
            $state = 'bekerja';
        }

        return view('operator.absen', compact('user', 'attendance', 'state'));
    }

    public function store(Request $request, string $type)
    {
        $data = $request->validate([
            'lat'      => ['required', 'numeric', 'between:-90,90'],
            'lng'      => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0'],
            'photo'    => ['required', 'image', 'max:8192'],
        ], [
            'lat.required'   => 'Lokasi belum terbaca. Tekan Perbarui.',
            'lng.required'   => 'Lokasi belum terbaca. Tekan Perbarui.',
            'photo.required' => 'Foto wajah wajib diambil.',
            'photo.image'    => 'File harus berupa foto.',
            'photo.max'      => 'Ukuran foto maksimal 8 MB.',
        ]);

        $user = $request->user();
        $site = $user->site;
        abort_unless($site, 422, 'Akun Anda belum punya area pabrik. Hubungi monitoring.');

        $now = now();
        $attendance = Attendance::currentFor($user);

        if ($type === 'masuk') {
            if ($attendance?->check_in_at) {
                return back()->withErrors(['absen' => 'Anda sudah absen masuk.']);
            }

            $attendance ??= (new Attendance())->forceFill([
                'user_id'   => $user->id,
                'work_date' => $now->toDateString(),
                'status'    => 'hadir',
            ]);

             $attendance->forceFill(['status' => 'hadir']);

        } else {
            if (! $attendance?->check_in_at || $attendance->check_out_at) {
                return back()->withErrors(['absen' => 'Belum ada absen masuk yang bisa dipulangkan.']);
            }
        }

        // Server menghitung jarak sendiri (nilai dari browser tidak dipercaya)
        $jarak = Geo::distanceMeters(
            (float) $data['lat'],
            (float) $data['lng'],
            (float) $site->latitude,
            (float) $site->longitude
        );

        $path = $request->file('photo')->store('absensi/' . $now->format('Y/m'), 'public');

        $prefix = $type === 'masuk' ? 'check_in' : 'check_out';

        $attendance->forceFill([
            "{$prefix}_at"           => $now,
            "{$prefix}_source"       => 'operator',
            "{$prefix}_lat"          => $data['lat'],
            "{$prefix}_lng"          => $data['lng'],
            "{$prefix}_accuracy_m"   => min(65535, (int) round($data['accuracy'])),
            "{$prefix}_distance_m"   => $jarak,
            "{$prefix}_outside_area" => $jarak > $site->radius_m,
            "{$prefix}_photo"        => $path,
        ])->save();

        return redirect()
            ->route('operator.absen')
            ->with('status', 'Absen ' . $type . ' tercatat pukul ' . $now->format('H.i') . '.');
    }

}
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AreaController extends Controller
{
    public function show(Request $request)
    {
        $site = $request->user()->site;

        abort_unless($site, 404, 'Akun Anda belum terhubung ke area pabrik.');

        return view('monitoring.area', ['site' => $site]);
    }

    public function update(Request $request)
    {
        $site = $request->user()->site;

        abort_unless($site, 404, 'Akun Anda belum terhubung ke area pabrik.');

        $data = $request->validate([
            'name'      => ['required', 'string', 'max:100'],
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_m'  => ['required', 'integer', 'between:20,2000'],
        ], [
            'name.required'      => 'Nama area wajib diisi.',
            'latitude.required'  => 'Latitude wajib diisi.',
            'latitude.numeric'   => 'Latitude harus berupa angka.',
            'latitude.between'   => 'Latitude harus di antara -90 dan 90.',
            'longitude.required' => 'Longitude wajib diisi.',
            'longitude.numeric'  => 'Longitude harus berupa angka.',
            'longitude.between'  => 'Longitude harus di antara -180 dan 180.',
            'radius_m.required'  => 'Radius wajib diisi.',
            'radius_m.integer'   => 'Radius harus berupa bilangan bulat (meter).',
            'radius_m.between'   => 'Radius harus di antara 20 dan 2000 meter.',
        ]);

        $site->forceFill([
            'name'      => $data['name'],
            'latitude'  => round((float) $data['latitude'], 7),
            'longitude' => round((float) $data['longitude'], 7),
            'radius_m'  => (int) $data['radius_m'],
        ])->save();

        return redirect()
            ->route('monitoring.area')
            ->with('status', 'Area absen berhasil disimpan. Berlaku untuk absen berikutnya.');
    }
}
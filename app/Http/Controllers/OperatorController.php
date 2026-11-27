<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSystem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperatorController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'cari'   => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif'])],
        ]);

        $cari = trim((string) $request->query('cari', ''));
        $status = $request->query('status');

        $query = User::where('role', 'operator')
            ->with('shift', 'workSystem')
            ->orderBy('username');

        if ($cari !== '') {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$cari}%")
                ->orWhere('username', 'like', "%{$cari}%"));
        }

        if ($status === 'aktif') {
            $query->where('is_active', true);
        } elseif ($status === 'nonaktif') {
            $query->where('is_active', false);
        }

        return view('monitoring.operator.index', [
            'operators' => $query->get(),
            'total'     => User::where('role', 'operator')->count(),
            'aktif'     => User::where('role', 'operator')->where('is_active', true)->count(),
            'nonaktif'  => User::where('role', 'operator')->where('is_active', false)->count(),
            'cari'      => $cari,
            'status'    => $status,
        ]);
    }

    public function create()
    {
        return view('monitoring.operator.form', [
            'operator' => null,
            'shifts'   => Shift::orderBy('start_time')->get(),
            'sistem'   => WorkSystem::orderBy('id')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturan(), $this->pesan());

        User::create([
            'name'           => $data['name'],
            'username'       => strtolower($data['username']),
            'password'       => $data['password'],
            'role'           => 'operator',
            'site_id'        => $request->user()->site_id,
            'shift_id'       => $data['shift_id'],
            'work_system_id' => $data['work_system_id'],
            'is_active'      => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('monitoring.operator.index')
            ->with('status', "Operator {$data['name']} berhasil ditambahkan.");
    }

    public function edit(User $operator)
    {
        abort_unless($operator->role === 'operator', 404);

        return view('monitoring.operator.form', [
            'operator' => $operator,
            'shifts'   => Shift::orderBy('start_time')->get(),
            'sistem'   => WorkSystem::orderBy('id')->get(),
        ]);
    }

    public function update(Request $request, User $operator)
    {
        abort_unless($operator->role === 'operator', 404);

        $data = $request->validate($this->aturan($operator), $this->pesan());

        $operator->fill([
            'name'           => $data['name'],
            'username'       => strtolower($data['username']),
            'shift_id'       => $data['shift_id'],
            'work_system_id' => $data['work_system_id'],
            'is_active'      => $request->boolean('is_active'),
        ]);

        // Kata sandi hanya diganti kalau diisi
        if (! empty($data['password'])) {
            $operator->password = $data['password'];
        }

        $operator->save();

        return redirect()
            ->route('monitoring.operator.index')
            ->with('status', "Data {$operator->name} berhasil disimpan.");
    }

    /**
     * Aktifkan atau nonaktifkan operator. Tidak ada hapus permanen
     * supaya riwayat absensinya tetap utuh.
     */
    public function toggle(User $operator)
    {
        abort_unless($operator->role === 'operator', 404);

        $operator->is_active = ! $operator->is_active;
        $operator->save();

        $pesan = $operator->is_active
            ? "{$operator->name} diaktifkan kembali."
            : "{$operator->name} dinonaktifkan. Riwayat absensinya tetap tersimpan.";

        return back()->with('status', $pesan);
    }

    private function aturan(?User $operator = null): array
    {
        $username = ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Za-z0-9._-]+$/'];
        $username[] = Rule::unique('users', 'username')->ignore($operator?->id);

        return [
            'name'           => ['required', 'string', 'max:100'],
            'username'       => $username,
            'password'       => $operator
                ? ['nullable', 'string', 'min:6', 'max:100']
                : ['required', 'string', 'min:6', 'max:100'],
            'shift_id'       => ['required', 'exists:shifts,id'],
            'work_system_id' => ['required', 'exists:work_systems,id'],
        ];
    }

    private function pesan(): array
    {
        return [
            'name.required'           => 'Nama operator wajib diisi.',
            'username.required'       => 'ID pengguna wajib diisi.',
            'username.min'            => 'ID pengguna minimal 3 karakter.',
            'username.regex'          => 'ID pengguna hanya boleh huruf, angka, titik, strip, dan garis bawah (tanpa spasi).',
            'username.unique'         => 'ID pengguna ini sudah dipakai.',
            'password.required'       => 'Kata sandi wajib diisi.',
            'password.min'            => 'Kata sandi minimal 6 karakter.',
            'shift_id.required'       => 'Pilih shift.',
            'shift_id.exists'         => 'Shift tidak valid.',
            'work_system_id.required' => 'Pilih sistem kerja.',
            'work_system_id.exists'   => 'Sistem kerja tidak valid.',
        ];
    }
}
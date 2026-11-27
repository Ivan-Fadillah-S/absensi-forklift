<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'consent'  => ['accepted'],
        ], [
            'username.required' => 'ID pengguna wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
            'consent.accepted'  => 'Centang persetujuan terlebih dahulu.',
        ]);

        $berhasil = Auth::attempt([
            'username'  => $data['username'],
            'password'  => $data['password'],
            'is_active' => true,
        ]);

        if (! $berhasil) {
            return back()
                ->withErrors(['username' => 'ID pengguna atau kata sandi salah.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
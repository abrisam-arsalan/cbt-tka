<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pintu masuk login KHUSUS admin di /admin/login.
 *
 * Dipisah dari halaman login siswa (/login) supaya siswa tidak mengetahui
 * ada halaman administrator. Akun siswa yang mencoba masuk di sini ditolak
 * dengan pesan generik (tanpa membocorkan info akun).
 */
class AdminLoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/AdminLogin', [
            'title' => 'Masuk Administrator',
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        if (! $user->isAdmin()) {
            // Akun non-admin tidak boleh masuk lewat pintu ini.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => 'Akun ini tidak terdaftar sebagai administrator.',
            ]);
        }

        $user->last_login_at = now();
        $user->last_login_ip = (string) $request->ip();
        $user->save();

        $request->session()->regenerate();

        return redirect()->intended(route('admin.dashboard'));
    }
}

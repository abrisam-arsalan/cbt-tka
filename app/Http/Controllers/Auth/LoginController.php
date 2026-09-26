<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman login SISWA di /login (alamat yang dibagikan ke siswa/kartu ujian).
 * Akun admin sengaja ditolak di sini — admin memakai pintu terpisah di
 * /admin/login (lihat AdminLoginController).
 */
class LoginController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/Login', [
            'title' => 'Masuk',
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        if ($user->isAdmin()) {
            // Admin tidak masuk lewat halaman siswa — matikan sesi dan suruh
            // lewat pintu khusus. Pesan ini hanya muncul bila kredensial admin
            // memang benar, jadi tidak membantu penebakan akun.
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages([
                'username' => 'Akun administrator harus masuk melalui halaman login khusus admin.',
            ]);
        }

        $user->last_login_at = now();
        $user->last_login_ip = (string) $request->ip();
        $user->save();

        $request->session()->regenerate();

        return redirect()->intended(route('student.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $wasAdmin = $request->user()?->isAdmin() ?? false;

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Setelah logout, arahkan kembali ke halaman login sesuai perannya.
        return redirect($wasAdmin ? route('admin.login') : route('login'));
    }
}

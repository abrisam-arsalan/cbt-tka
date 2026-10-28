<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

/**
 * Handoff portal → CBT (PRD v2 §6).
 *
 * Alur (sesuai keputusan: tombol redirect, TANPA auto-login penuh):
 *   1. Portal menerbitkan kode sekali-pakai (TTL ≤60 dtk, terikat user).
 *   2. Portal redirect ke /sso?kode=... (tombol "Mesin Ujian").
 *   3. Controller ini verifikasi kode ke portal SERVER-ke-SERVER
 *      (shared secret CBT_HANDOFF_SECRET) lalu Auth::login() tanpa
 *      password lokal — kode dibuang dari address bar.
 *   4. Halaman login CBT lama TETAP berfungsi sebagai fallback
 *      (syarat ujian berisiko tinggi — portal mati ≠ ujian mati).
 */
class HandoffController extends Controller
{
    public function show(Request $request): RedirectResponse
    {
        $code = (string) $request->query('kode', '');

        if ($code === '') {
            return redirect()->route('login');
        }

        $portalBase = rtrim((string) config('cbt.portal_base_url', ''), '/');
        $secret = (string) config('cbt.handoff_secret', '');

        if ($portalBase === '' || $secret === '') {
            // Handoff belum dikonfigurasi → fallback ke login manual.
            return redirect()->route('login');
        }

        try {
            $res = Http::timeout(5)->post($portalBase.'/api/handoff/verify', [
                'code' => $code,
                'secret' => $secret,
            ]);
        } catch (\Throwable) {
            return redirect()->route('login');
        }

        if (! $res->ok() || ! ($res->json('ok') ?? false)) {
            return redirect()->route('login');
        }

        $username = (string) $res->json('username', '');
        if ($username === '') {
            return redirect()->route('login');
        }

        $user = User::where('username', $username)->first();
        if (! $user || ! $user->is_active) {
            return redirect()->route('login');
        }

        Auth::login($user, false);
        $request->session()->regenerate();

        // Kode dibuang dari address bar — redirect bersih ke tujuan.
        return $user->isAdmin() || $user->isGuru()
            ? redirect()->route('admin.dashboard')
            : redirect()->intended(route('student.dashboard'));
    }
}

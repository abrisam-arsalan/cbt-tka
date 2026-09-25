<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil akun admin yang sedang login — hanya untuk mengubah nama tampil
 * dan password. Manajemen akun siswa ada di menu Siswa; pengaturan aplikasi
 * ada di menu Pengaturan.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Profile', [
            'title' => 'Profil Saya',
            'user' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role_label' => $user->role->label(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'current_password' => ['nullable', 'string'],
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ], [
            'password.min' => 'Password baru minimal 6 karakter.',
            'password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ]);

        $user->name = $data['name'];

        if (! empty($data['password'])) {
            if (empty($data['current_password']) || ! Hash::check($data['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'Password saat ini salah.']);
            }
            $user->password = $data['password']; // di-hash lewat cast
        }

        $user->save();

        $this->audit->log(
            action: 'profile.updated',
            subject: $user,
            description: 'Profil diperbarui'.(! empty($data['password']) ? ' (termasuk password).' : '.'),
        );

        return back()->with('success', 'Profil berhasil diperbarui.');
    }
}

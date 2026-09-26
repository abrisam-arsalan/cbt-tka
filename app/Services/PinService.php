<?php

namespace App\Services;

use App\Models\User;

/**
 * PIN login siswa: 6 digit ANGKA, sengaja dibedakan dari username (NISN)
 * supaya kredensial di kartu ujian tidak bisa ditebak dari satu kolom saja.
 *
 * Disimpan dua kali seperti token ujian:
 *   password    : hash bcrypt, dipakai untuk login.
 *   pin_cipher  : plaintext terenkripsi APP_KEY, hanya untuk mencetak ulang
 *                 kartu ujian tanpa reset PIN.
 */
class PinService
{
    /**
     * PIN acak 6 digit. Pastikan tidak sama dengan username.
     */
    public function generate(?string $username = null): string
    {
        do {
            $pin = (string) random_int(100000, 999999);
        } while ($username !== null && $pin === $username);

        return $pin;
    }

    /**
     * Pasang PIN ke instance User SEBELUM disimpan: kolom password di-hash
     * lewat cast 'hashed', salinan plaintext masuk ke pin_cipher.
     */
    public function apply(User $user, string $pin): void
    {
        $user->password = $pin;
        $user->forceFill(['pin_cipher' => $pin]);
    }

    /**
     * PIN plaintext untuk dicetak di kartu (null bila tidak pernah tercatat).
     */
    public function plain(?User $user): ?string
    {
        return $user?->pin_cipher; // cast 'encrypted' mendekrip otomatis
    }
}

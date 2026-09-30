<?php

namespace App\Services;

use App\Models\Exam;

/**
 * Token ujian: SATU token tetap per ujian, berlaku selama ujian berlangsung.
 *
 * Pengawas melihat token di halaman Monitoring dan mengumumkannya; siswa
 * memasukkannya saat akan mulai (join). Token TIDAK lagi berganti tiap
 * 30 menit — dulu berbasis waktu, kini berbasis ujian, karena rotasi waktu
 * membuat sebagian siswa "gagal memasukkan token padahal sudah benar"
 * (token diumumkan window lama, diverifikasi saat window baru).
 *
 * Token dibuat sekali (lazy) lalu disimpan sebagai hash (verifikasi) dan
 * cipher (dipakai untuk ditampilkan lagi). Token lama hasil upgrade/rotasi
 * manual masih diterima satu langkah ke belakang (previous_token_hash) agar
 * siswa yang sedang mengetik tidak tertolak saat perpindahan skema.
 */
class ExamSessionTokenService
{
    /** Nilai penanda "token menetap" pada kolom session_window. */
    public const FIXED_WINDOW = 0;

    /**
     * Token aktif untuk ujian — dibuat sekali, tidak pernah berganti sendiri.
     *
     * @return array{token: string, expires_at: null, window: int}
     */
    public function current(Exam $exam): array
    {
        if ($exam->session_token_cipher === null || (int) $exam->session_window !== self::FIXED_WINDOW) {
            $this->rotate($exam);
        }

        return [
            'token' => (string) $exam->session_token_cipher, // cast 'encrypted' mendekrip
            'expires_at' => null, // tidak ada kedaluwarsa waktu
            'window' => self::FIXED_WINDOW,
        ];
    }

    /**
     * Cocokkan token yang dimasukkan siswa dengan token ujian (plus token
     * sebelumnya untuk masa transisi, lihat catatan kelas).
     */
    public function verify(Exam $exam, string $input): bool
    {
        $normalized = TokenGenerator::fromConfig()->normalize($input);

        if ($normalized === '') {
            return false;
        }

        $hash = hash('sha256', $normalized);

        // Pastikan token sudah terbentuk (exam baru, belum pernah dibuka di monitoring).
        $this->current($exam);

        if (hash_equals((string) $exam->session_token_hash, $hash)) {
            return true;
        }

        return $exam->previous_token_hash !== null
            && hash_equals($exam->previous_token_hash, $hash);
    }

    /**
     * Ganti token secara manual (mis. token bocor/tersebar). Token yang
     * digantikan tetap diterima sampai rotasi berikutnya — transisi aman.
     */
    public function rotate(Exam $exam): string
    {
        $raw = TokenGenerator::fromConfig()->generateRaw();

        $exam->forceFill([
            'session_token_hash' => hash('sha256', $raw),
            'session_token_cipher' => TokenGenerator::fromConfig()->format($raw),
            'session_window' => self::FIXED_WINDOW,
            'previous_token_hash' => $exam->session_token_hash,
            'previous_token_window' => $exam->session_window,
        ])->save();

        return (string) $exam->session_token_cipher;
    }
}

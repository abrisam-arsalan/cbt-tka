<?php

namespace App\Services;

use App\Models\Exam;

/**
 * Token SESI ujian: satu token untuk seluruh peserta SATU ujian, yang
 * berganti otomatis setiap 30 menit mengikuti jam dinding (window :00 dan
 * :30). Admin/pengawas melihat token aktif di halaman Monitoring dan
 * mengumumkannya; siswa memasukkannya saat akan mulai ujian.
 *
 * Rotasi bersifat lazy: token window baru dibuat saat pertama kali diminta
 * (dibuka di monitoring atau diverifikasi saat join) pada window berjalan.
 * Karena window ditentukan waktu dinding — bukan waktu pembuatan — semua
 * server/perangkat otomatis sepakat kapan token berganti.
 */
class ExamSessionTokenService
{
    public const WINDOW_SECONDS = 1800; // 30 menit

    /**
     * Token aktif untuk ujian, digenerate ulang bila window sudah berganti.
     *
     * @return array{token: string, expires_at: string, window: int}
     */
    public function current(Exam $exam): array
    {
        $window = $this->window();

        if ((int) $exam->session_window !== $window || $exam->session_token_cipher === null) {
            $this->rotate($exam, $window);
        }

        return [
            'token' => (string) $exam->session_token_cipher, // cast 'encrypted' mendekrip
            'expires_at' => $this->windowEnd($window)->toIso8601String(),
            'window' => $window,
        ];
    }

    /**
     * Cocokkan token yang dimasukkan siswa dengan token window SAAT ini
     * dan window sebelumnya (toleransi 30 menit agar siswa yang sedang
     * mengetik saat token berganti tidak gagal).
     */
    public function verify(Exam $exam, string $input): bool
    {
        $normalized = TokenGenerator::fromConfig()->normalize($input);
        $hash = hash('sha256', $normalized);

        if ($hash === '') {
            return false;
        }

        $current = $this->current($exam);

        if ($hash === $exam->session_token_hash) {
            return true;
        }

        // Window sebelumnya: token lama masih diterima sampai 30 menit setelah
        // berganti (grace), selama exam belum pernah di-rotate manual.
        $previousWindow = $this->window() - 1;

        return $exam->previous_token_hash !== null
            && (int) $exam->previous_token_window === $previousWindow
            && hash_equals($exam->previous_token_hash, $hash);
    }

    /**
     * Paksa rotasi sekarang (dipakai saat rotate lazy maupun manual).
     */
    public function rotate(Exam $exam, ?int $window = null): string
    {
        $window ??= $this->window();
        $raw = TokenGenerator::fromConfig()->generateRaw();

        // Simpan hash token lama sebagai grace window berikutnya.
        $previousHash = $exam->session_token_hash;
        $previousWindow = $exam->session_window;

        $exam->forceFill([
            'session_token_hash' => hash('sha256', $raw),
            'session_token_cipher' => TokenGenerator::fromConfig()->format($raw),
            'session_window' => $window,
            'previous_token_hash' => $previousHash,
            'previous_token_window' => $previousWindow,
        ])->save();

        return (string) $exam->session_token_cipher;
    }

    private function window(): int
    {
        return intdiv(now()->timestamp, self::WINDOW_SECONDS);
    }

    private function windowEnd(int $window): \Illuminate\Support\Carbon
    {
        return now()->setTimestamp(($window + 1) * self::WINDOW_SECONDS)->startOfMinute();
    }
}

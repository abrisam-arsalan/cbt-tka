<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\User;
use Illuminate\Support\Facades\URL;

/**
 * Kartu ujian.
 *
 * Kartu hanya memuat AKUN LOGIN siswa: username (NISN) + PIN, plus URL
 * login. Token TIDAK dicetak lagi per siswa — kini satu token sesi per
 * ujian yang berganti otomatis tiap 30 menit dan diumumkan pengawas dari
 * halaman Monitoring (lihat ExamSessionTokenService).
 */
class CardService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly SettingService $settings,
        private readonly PinService $pins,
    ) {}

    /**
     * Data satu kartu ujian untuk dicetak.
     *
     * @return array<string, mixed>
     */
    public function cardData(ExamParticipant $participant): array
    {
        $participant->loadMissing(['user.schoolClass', 'exam']);

        return [
            'school_name' => $this->settings->get('app.school_name') ?: config('cbt.card.school_name'),
            'school_city' => $this->settings->get('app.school_city') ?: '',
            'logo_url' => $this->resolveLogoUrl(),
            'student_name' => $participant->user?->name ?? '(siswa terhapus)',
            'username' => $participant->user?->username,
            'login_pin' => $this->safePin($participant->user),
            'login_url' => $this->loginUrl(),
            'nisn' => $participant->user?->nisn,
            'class_name' => $participant->user?->schoolClass?->name ?? '-',
            'exam_title' => $participant->exam?->title ?? '(ujian terhapus)',
            'exam_description' => $participant->exam?->description,
            'duration_minutes' => (int) ($participant->exam?->duration_minutes ?? 0),
            'start_at' => $participant->exam?->start_at?->format('d/m/Y H:i'),
            'end_at' => $participant->exam?->end_at?->format('d/m/Y H:i'),
            'rules' => $this->examRules($participant),
        ];
    }

    /**
     * Decrypt pin_cipher dengan aman: data dari APP_KEY lama tidak boleh
     * membuat halaman kartu 500 — kembalikan null bila gagal.
     */
    private function safePin(?User $user): ?string
    {
        try {
            return $this->pins->plain($user);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Data seluruh kartu ujian untuk cetak massal.
     *
     * @return array<int, array<string, mixed>>
     */
    public function bulkCardData(Exam $exam): array
    {
        return $exam->participants()
            ->with('user.schoolClass')
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(fn (ExamParticipant $participant) => $this->cardData($participant))
            ->all();
    }

    /**
     * URL halaman login — dicetak sbg teks di kartu agar bisa dibuka/diketik
     * dari HP siswa (di luar PC laboratorium sekolah).
     */
    private function loginUrl(): string
    {
        try {
            return URL::route('login');
        } catch (\Throwable) {
            return URL::to('/login');
        }
    }

    private function resolveLogoUrl(): ?string
    {
        $path = config('cbt.card.logo_path');

        if (! is_string($path) || $path === '') {
            return null;
        }

        try {
            return asset('storage/'.$path);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Aturan singkat untuk dicetak di kartu ujian.
     *
     * @return array<int, string>
     */
    private function examRules(ExamParticipant $participant): array
    {
        $rules = [
            'Bawa kartu ini saat ujian berlangsung. Login memakai Username + PIN pada kartu.',
            'Token ujian diumumkan pengawas di ruang ujian dan berganti setiap 30 menit.',
            'Masuk ke ruang ujian minimal 10 menit sebelum jadwal mulai.',
            'Pastikan perangkat terisi penuh atau terhubung charger.',
            'Tidak diperkenankan membuka tab atau aplikasi lain selama ujian.',
            'Jika koneksi terputus, jawaban tersimpan otomatis dan akan dikirim saat kembali online.',
            'Tanyakan kepada pengawas bila ada kendala teknis sebelum menekan tombol kumpulkan.',
        ];

        if ((bool) $participant->exam?->anti_cheat_enabled) {
            array_unshift($rules, 'Ujian ini mengaktifkan deteksi kecurangan. Jendela ujian akan dicatat.');
        }

        return $rules;
    }
}

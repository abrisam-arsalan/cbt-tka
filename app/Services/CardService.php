<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\ExamParticipant;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;

/**
 * Manajemen token dan kartu ujian.
 *
 * Token disimpan dua kali:
 *   token_hash    SHA-256 hex, diindeks unik, dipakai untuk verifikasi join.
 *   token_cipher  plaintext terenkripsi (cast "encrypted"), dipakai hanya
 *                 saat mencetak ulang kartu ujian.
 *
 * Dengan skema ini database yang bocor hanya memperlihatkan hash (tidak bisa
 * dipakai login), tapi admin tetap bisa mencetak ulang kartu tanpa harus
 * regenerate token.
 */
class CardService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Generate token baru. Mengembalikan plaintext yang siap dicetak.
     */
    public function generateToken(ExamParticipant $participant): string
    {
        return $this->regenerateToken($participant, actor: null);
    }

    public function regenerateToken(ExamParticipant $participant, $actor = null): string
    {
        $generator = TokenGenerator::fromConfig();
        $raw = $generator->generateRaw();
        $hash = hash('sha256', $raw);

        $participant->forceFill([
            'token_hash' => $hash,
            'token_cipher' => $raw,
            'token_generated_at' => now(),
            'is_active' => true,
        ])->save();

        $this->audit->log(
            action: 'exam.token.regenerated',
            subject: $participant,
            description: "Token ujian digenerate ulang untuk {$participant->user?->username}.",
        );

        return $generator->format($raw);
    }

    /**
     * Verifikasi token yang dimasukkan siswa saat join ujian.
     */
    public function verify(Exam $exam, string $rawToken): ?ExamParticipant
    {
        $generator = TokenGenerator::fromConfig();
        $hash = hash('sha256', $generator->normalize($rawToken));

        return ExamParticipant::query()
            ->where('exam_id', $exam->id)
            ->where('token_hash', $hash)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Data satu kartu ujian untuk dicetak.
     *
     * @return array<string, mixed>
     */
    public function cardData(ExamParticipant $participant): array
    {
        $participant->loadMissing(['user.schoolClass', 'exam']);

        $generator = TokenGenerator::fromConfig();
        $plain = $participant->plainToken();
        $formatted = $plain !== null ? $generator->format($plain) : null;

        return [
            'school_name' => config('cbt.card.school_name'),
            'logo_url' => $this->resolveLogoUrl(),
            'student_name' => $participant->user?->name ?? '(siswa terhapus)',
            'username' => $participant->user?->username,
            'nisn' => $participant->user?->nisn,
            'class_name' => $participant->user?->schoolClass?->name ?? '-',
            'exam_title' => $participant->exam?->title ?? '(ujian terhapus)',
            'exam_description' => $participant->exam?->description,
            'duration_minutes' => (int) ($participant->exam?->duration_minutes ?? 0),
            'start_at' => $participant->exam?->start_at?->format('d/m/Y H:i'),
            'end_at' => $participant->exam?->end_at?->format('d/m/Y H:i'),
            'token' => $formatted,
            'token_generated_at' => $participant->token_generated_at?->format('d/m/Y H:i'),
            'qr_svg' => $formatted !== null ? $this->renderJoinQrSvg($participant->exam, $formatted) : null,
            'qr_size' => (int) config('cbt.card.qr_size', 180),
            'rules' => $this->examRules($participant),
        ];
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
     * Generate token untuk seluruh peserta yang belum punya token.
     *
     * @return array<int, string> keyed by participant id
     */
    public function ensureTokensForExam(Exam $exam): array
    {
        $generated = [];

        $exam->participants()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('token_hash')
                    ->orWhere('token_hash', '');
            })
            ->each(function (ExamParticipant $participant) use (&$generated) {
                $generated[$participant->id] = $this->generateToken($participant);
            });

        return $generated;
    }

    /**
     * SVG QR code berisi URL join + token.
     *
     * Dipakai sebagai gambar inline di kartu ujian dan tidak memerlukan
     * endpoint terpisah, jadi browser yang offline tetap bisa menampilkan kartu.
     */
    public function renderJoinQrSvg(Exam $exam, string $formattedToken): string
    {
        $url = $this->joinUrl($exam, $formattedToken);

        $renderer = new ImageRenderer(
            new RendererStyle((int) config('cbt.card.qr_size', 180)),
            new SvgImageBackEnd,
        );

        return (new Writer($renderer))->writeString($url);
    }

    private function joinUrl(Exam $exam, string $formattedToken): string
    {
        try {
            return Route::has('student.exam.join-with-token')
                ? URL::signedRoute('student.exam.join-with-token', [
                    'exam' => $exam->id,
                    'token' => $formattedToken,
                ])
                : URL::to('/ujian/join', ['exam' => $exam->id, 'token' => $formattedToken]);
        } catch (\Throwable) {
            return URL::to('/ujian/join', ['exam' => $exam->id, 'token' => $formattedToken]);
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
            'Bawa kartu ini saat ujian berlangsung. Token hanya berlaku untuk satu siswa.',
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

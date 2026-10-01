<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Services\Presence\PresenceDriver;
use Illuminate\Support\Carbon;

/**
 * Titik masuk tunggal untuk seluruh urusan presence siswa.
 *
 * Pemanggil tidak perlu tahu driver mana yang aktif. Bila Redis dikonfigurasi
 * tapi tidak bisa dihubungi, AppServiceProvider sudah menjatuhkan pilihan ke
 * DatabasePresenceDriver sebelum service ini dibuat.
 */
class PresenceService
{
    public function __construct(private readonly PresenceDriver $driver) {}

    public function driverName(): string
    {
        return $this->driver->name();
    }

    /**
     * Catat heartbeat siswa.
     *
     * @param  array<string, mixed>  $meta
     */
    public function ping(Attempt $attempt, array $meta = []): void
    {
        $this->driver->ping($attempt, $meta);
    }

    public function forget(Attempt $attempt): void
    {
        $this->driver->forget($attempt);
    }

    public function isOnline(int $attemptId): bool
    {
        return $this->driver->isOnline($attemptId);
    }

    public function lastSeen(int $attemptId): ?Carbon
    {
        return $this->driver->lastSeen($attemptId);
    }

    public function prune(): int
    {
        return $this->driver->prune(now()->subMinutes(120));
    }

    /**
     * Baris monitoring untuk dashboard admin.
     *
     * Berbasis DAFTAR PESERTA, bukan attempt: siswa yang belum login sama
     * sekali tetap tampil dengan status 'belum_login' agar pengawas mudah
     * mendeteksi siapa yang belum mengikuti ujian. Peserta yang sudah punya
     * attempt menampilkan data attempt + presence (volatile) — tetap hemat,
     * total hanya beberapa query meski halaman di-refresh berkala.
     *
     * @return array<int, array<string, mixed>>
     */
    public function monitoringRows(Exam $exam): array
    {
        $participants = ExamParticipant::query()
            ->active()
            ->with('user.schoolClass')
            ->where('exam_id', $exam->id)
            ->orderBy('id')
            ->get();

        $attempts = Attempt::query()
            // Dua agregat dalam satu query. Dihitung di sini, bukan per baris,
            // karena halaman monitoring bisa menampilkan 200 attempt sekaligus.
            ->withCount([
                'answers',
                'answers as late_answers_count' => fn ($query) => $query->where('late_sync_flag', true),
            ])
            ->where('exam_id', $exam->id)
            ->get()
            ->keyBy('user_id');

        if ($participants->isEmpty() && $attempts->isEmpty()) {
            return [];
        }

        $presence = $attempts->isNotEmpty() ? $this->driver->statesForExam($exam->id) : [];
        $now = now();
        $effectiveTotal = $exam->effectiveQuestionCount();

        $rows = $participants->map(function (ExamParticipant $p) use ($attempts, $presence, $now, $effectiveTotal) {
            $attempt = $attempts->get($p->user_id);

            if ($attempt === null) {
                // Belum login sama sekali — bukan attempt, hanya baris roster.
                return [
                    'attempt_id' => null,
                    'user_id' => (int) $p->user_id,
                    'name' => $p->user?->name ?? '(user terhapus)',
                    'username' => $p->user?->username,
                    'class_name' => $p->user?->schoolClass?->name ?? '-',
                    'status' => 'belum_login',
                    'status_label' => 'Belum Login',
                    'answered_count' => 0,
                    'total_questions' => $effectiveTotal,
                    'progress_percent' => 0.0,
                    'remaining_seconds' => null,
                    'deadline_at' => null,
                    'warnings_count' => 0,
                    'online' => false,
                    'presence_status' => 'absent',
                    'last_seen_at' => null,
                    'outbox_pending' => 0,
                    'score' => null,
                    'submit_reason' => null,
                    'late_answers_count' => 0,
                ];
            }

            $state = $presence[$attempt->id] ?? null;
            $lastSeen = $state['last_seen_at'] ?? null;

            return [
                'attempt_id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'name' => $attempt->user?->name ?? $p->user?->name ?? '(user terhapus)',
                'username' => $attempt->user?->username ?? $p->user?->username,
                'class_name' => $attempt->user?->schoolClass?->name ?? $p->user?->schoolClass?->name ?? '-',
                'status' => $attempt->status->value,
                'status_label' => $attempt->status->label(),
                'answered_count' => (int) $attempt->answers_count,
                'total_questions' => (int) $attempt->total_questions,
                'progress_percent' => $attempt->progressPercent(),
                'remaining_seconds' => $attempt->remainingSeconds($now),
                'deadline_at' => $attempt->deadline_at?->toIso8601String(),
                'warnings_count' => (int) $attempt->warnings_count,
                'online' => $state !== null,
                'presence_status' => $state['status'] ?? 'offline',
                'last_seen_at' => $lastSeen,
                'outbox_pending' => (int) ($state['outbox_pending'] ?? 0),
                'score' => $attempt->score === null ? null : (float) $attempt->score,
                'submit_reason' => $attempt->submit_reason?->value,
                'late_answers_count' => (int) $attempt->late_answers_count,
            ];
        })->all();

        // Attempt yatim (peserta dihapus tapi attempt-nya masih ada) tetap
        // ditampilkan supaya pengawas tidak kehilangan jejak siswa di layar.
        $missing = $attempts->reject(fn (Attempt $a) => $participants->contains('user_id', $a->user_id));

        foreach ($missing as $attempt) {
            $state = $presence[$attempt->id] ?? null;

            $rows[] = [
                'attempt_id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'name' => $attempt->user?->name ?? '(peserta dihapus)',
                'username' => $attempt->user?->username,
                'class_name' => $attempt->user?->schoolClass?->name ?? '-',
                'status' => $attempt->status->value,
                'status_label' => $attempt->status->label(),
                'answered_count' => (int) $attempt->answers_count,
                'total_questions' => (int) $attempt->total_questions,
                'progress_percent' => $attempt->progressPercent(),
                'remaining_seconds' => $attempt->remainingSeconds($now),
                'deadline_at' => $attempt->deadline_at?->toIso8601String(),
                'warnings_count' => (int) $attempt->warnings_count,
                'online' => $state !== null,
                'presence_status' => $state['status'] ?? 'offline',
                'last_seen_at' => $state['last_seen_at'] ?? null,
                'outbox_pending' => (int) ($state['outbox_pending'] ?? 0),
                'score' => $attempt->score === null ? null : (float) $attempt->score,
                'submit_reason' => $attempt->submit_reason?->value,
                'late_answers_count' => (int) $attempt->late_answers_count,
            ];
        }

        return $rows;
    }

    /**
     * Ringkasan kehadiran untuk kartu dashboard admin.
     *
     * Menerima baris hasil monitoringRows() supaya admin yang memanggil
     * keduanya dalam satu request tidak menjalankan query dua kali.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    public function summarize(array $rows): array
    {
        $count = fn (callable $predicate) => count(array_filter($rows, $predicate));

        // "Punya attempt" = sudah login/memulai. Belum login dihitung terpisah
        // agar kartu Offline hanya berisi siswa yang memulai lalu putus koneksi.
        $hasAttempt = fn (array $row) => $row['attempt_id'] !== null;

        $online = $count(fn (array $row) => $hasAttempt($row) && $row['online']);

        return [
            'total' => count($rows),
            'online' => $online,
            'offline' => $count($hasAttempt) - $online,
            'belum_login' => $count(fn (array $row) => ! $hasAttempt($row)),
            'submitted' => $count(fn (array $row) => $row['status'] === 'submitted'),
            'in_progress' => $count(fn (array $row) => $row['status'] === 'in_progress'),
            'locked' => $count(fn (array $row) => $row['status'] === 'locked'),
            'expired' => $count(fn (array $row) => $row['status'] === 'expired'),
            'with_warnings' => $count(fn (array $row) => $row['warnings_count'] > 0),
            'with_late_answers' => $count(fn (array $row) => $row['late_answers_count'] > 0),
        ];
    }
}

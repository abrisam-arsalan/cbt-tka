<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\Exam;
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
     * Menggabungkan data attempt (persisten) dengan presence (volatile) dalam
     * dua query saja — penting karena halaman ini di-refresh admin berkala
     * saat 200 siswa sedang mengerjakan ujian.
     *
     * @return array<int, array<string, mixed>>
     */
    public function monitoringRows(Exam $exam): array
    {
        $attempts = Attempt::query()
            ->with('user.schoolClass')
            // Dua agregat dalam satu query. Dihitung di sini, bukan per baris,
            // karena halaman monitoring bisa menampilkan 200 attempt sekaligus.
            ->withCount([
                'answers',
                'answers as late_answers_count' => fn ($query) => $query->where('late_sync_flag', true),
            ])
            ->where('exam_id', $exam->id)
            ->orderBy('id')
            ->get();

        if ($attempts->isEmpty()) {
            return [];
        }

        $presence = $this->driver->statesForExam($exam->id);
        $now = now();

        return $attempts->map(function (Attempt $attempt) use ($presence, $now) {
            $state = $presence[$attempt->id] ?? null;
            $lastSeen = $state['last_seen_at'] ?? null;

            return [
                'attempt_id' => $attempt->id,
                'user_id' => $attempt->user_id,
                'name' => $attempt->user?->name ?? '(user terhapus)',
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
                'last_seen_at' => $lastSeen,
                'outbox_pending' => (int) ($state['outbox_pending'] ?? 0),
                'score' => $attempt->score === null ? null : (float) $attempt->score,
                'submit_reason' => $attempt->submit_reason?->value,
                'late_answers_count' => (int) $attempt->late_answers_count,
            ];
        })->all();
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

        $online = $count(fn (array $row) => $row['online']);

        return [
            'total' => count($rows),
            'online' => $online,
            'offline' => count($rows) - $online,
            'submitted' => $count(fn (array $row) => $row['status'] === 'submitted'),
            'in_progress' => $count(fn (array $row) => $row['status'] === 'in_progress'),
            'locked' => $count(fn (array $row) => $row['status'] === 'locked'),
            'expired' => $count(fn (array $row) => $row['status'] === 'expired'),
            'with_warnings' => $count(fn (array $row) => $row['warnings_count'] > 0),
            'with_late_answers' => $count(fn (array $row) => $row['late_answers_count'] > 0),
        ];
    }
}

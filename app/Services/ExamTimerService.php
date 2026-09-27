<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\SubmitReason;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orkestrasi siklus hidup ujian dan attempt.
 *
 * Semua transisi status yang mengubah waktu atau skor melewati service ini.
 * Memanggilnya langsung dari controller membuat jejaknya mudah diaudit
 * dan memudahkan penulisan test.
 *
 * Tiga lintasan waktu (lihat komentar di migration create_attempts_table):
 *   deadline_at   batas pengerjaan siswa
 *   expires_at    deadline_at + offline_grace_minutes  (batas akhir sync)
 *   expired_at    cap waktu saat scheduler menandai kedaluwarsa
 */
class ExamTimerService
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly PresenceService $presence,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Mulai attempt untuk siswa, atau lanjutkan yang sudah ada.
     *
     * unique(exam_id, user_id) menjamin hanya ada satu attempt per siswa,
     * jadi "lanjutkan attempt" tidak pernah ambigu.
     */
    public function startAttempt(
        Exam $exam,
        User $user,
        ExamParticipant $participant,
        string $ip,
        string $userAgent,
    ): Attempt {
        $attempt = $this->findExistingAttempt($exam, $user);

        if ($attempt !== null) {
            // Catat saat pertama peserta menekan "mulai ujian" di kartu.
            if ($participant->first_joined_at === null) {
                $participant->first_joined_at = now();
                $participant->save();
            }

            return $attempt;
        }

        $now = now();

        return DB::transaction(function () use ($exam, $user, $participant, $now, $ip, $userAgent) {
            $deadline = $now->copy()->addMinutes((int) $exam->duration_minutes);
            $expires = $deadline->copy()->addMinutes($exam->graceMinutes());

            $attempt = Attempt::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'exam_participant_id' => $participant->id,
                'status' => AttemptStatus::InProgress,
                'started_at' => $now,
                'deadline_at' => $deadline,
                'expires_at' => $expires,
                'shuffle_seed' => random_int(1, PHP_INT_MAX),
                'total_questions' => $exam->effectiveQuestionCount(),
                'started_ip' => substr($ip, 0, 45),
                'user_agent' => substr($userAgent, 0, 255),
            ]);

            if ($participant->first_joined_at === null) {
                $participant->first_joined_at = $now;
                $participant->save();
            }

            $this->audit->log(
                action: 'attempt.started',
                subject: $attempt,
                description: "Siswa {$user->username} memulai ujian.",
            );

            return $attempt;
        });
    }

    /**
     * Submit attempt secara eksplisit.
     *
     * Idempoten: memanggilnya dua kali pada attempt yang sama tidak mengubah
     * skor atau menambah audit log baru.
     */
    public function submit(Attempt $attempt, SubmitReason $reason, ?string $ip = null): Attempt
    {
        if ($attempt->status === AttemptStatus::Submitted) {
            return $attempt;
        }

        return DB::transaction(function () use ($attempt, $reason, $ip) {
            $locked = Attempt::query()->whereKey($attempt->id)->lockForUpdate()->first();

            if ($locked === null || $locked->status === AttemptStatus::Submitted) {
                return $locked ?? $attempt;
            }

            $result = $this->scoring->scoreAttempt($locked, persist: false);

            $locked->forceFill(array_merge($result->toArray(), [
                'status' => AttemptStatus::Submitted,
                'submitted_at' => now(),
                'submit_reason' => $reason,
                'submitted_ip' => $ip !== null ? substr($ip, 0, 45) : $locked->submitted_ip,
            ]))->save();

            return $locked;
        });
    }

    /**
     * Scheduler pass 1: tandai attempt yang melewati deadline sebagai expired.
     *
     * Lewatkan attempt milik ujian yang sedang dijeda, karena pada ujian
     * dijeda deadline akan diperpanjang otomatis saat dilanjutkan.
     */
    public function markExpiredPass(): int
    {
        $now = now();
        $processed = 0;

        Attempt::query()
            ->whereIn('status', [
                AttemptStatus::InProgress->value,
                AttemptStatus::Locked->value,
            ])
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', $now)
            ->whereHas('exam', fn ($query) => $query
                ->where('status', '!=', ExamStatus::Paused->value))
            ->chunkById(
                (int) config('cbt.scheduler.expire_sweep_chunk', 200),
                function ($attempts) use ($now, &$processed) {
                    foreach ($attempts as $attempt) {
                        $attempt->forceFill([
                            'status' => AttemptStatus::Expired,
                            'expired_at' => $attempt->expired_at ?? $now,
                        ])->save();

                        $processed++;
                    }
                },
            );

        return $processed;
    }

    /**
     * Scheduler pass 2: submit attempt yang melewati expires_at (grace period).
     *
     * Juga menangkap attempt in_progress / locked yang lolos dari pass 1
     * (mis. server sempat mati lalu hidup kembali).
     */
    public function submitOverduePass(): int
    {
        $now = now();
        $processed = 0;

        Attempt::query()
            ->whereIn('status', [
                AttemptStatus::InProgress->value,
                AttemptStatus::Locked->value,
                AttemptStatus::Expired->value,
            ])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->whereHas('exam', fn ($query) => $query
                ->where('status', '!=', ExamStatus::Paused->value))
            ->chunkById(
                (int) config('cbt.scheduler.expire_sweep_chunk', 200),
                function ($attempts) use ($now, &$processed) {
                    foreach ($attempts as $attempt) {
                        $this->submit($attempt, SubmitReason::TimeUp);
                        $processed++;
                    }
                },
            );

        return $processed;
    }

    // ------------------------------------------------------------------
    // Aksi admin pada ujian
    // ------------------------------------------------------------------

    public function activateExam(Exam $exam, User $admin): Exam
    {
        $blockers = $exam->activationBlockers();

        if ($blockers !== []) {
            throw new ExamNotReadyException($blockers);
        }

        return $this->transitionExamStatus($exam, ExamStatus::Active, $admin, 'exam.activated');
    }

    /**
     * Jeda ujian: attempt aktif dikunci sementara dan deadline dibekukan.
     */
    public function pauseExam(Exam $exam, User $admin): Exam
    {
        $exam = DB::transaction(function () use ($exam, $admin) {
            $now = now();

            $exam->forceFill([
                'status' => ExamStatus::Paused,
                'paused_at' => $now,
            ])->save();

            Attempt::query()
                ->where('exam_id', $exam->id)
                ->where('status', AttemptStatus::InProgress->value)
                ->update([
                    'status' => AttemptStatus::Locked->value,
                    'lock_reason' => 'exam_paused',
                    'locked_at' => $now,
                ]);

            $this->audit->log(
                action: 'exam.paused',
                subject: $exam,
                description: 'Ujian dijeda. Attempt aktif dikunci sementara.',
            );

            return $exam;
        });

        return $exam;
    }

    /**
     * Lanjutkan ujian: durasi jeda ditambahkan ke deadline setiap attempt
     * yang terkunci karena jeda admin, bukan karena anti-cheat.
     */
    public function resumeExam(Exam $exam, User $admin): Exam
    {
        return DB::transaction(function () use ($exam, $admin) {
            $now = now();

            if ($exam->paused_at !== null) {
                $pauseSeconds = $now->getTimestamp() - $exam->paused_at->getTimestamp();

                // Chunk-by-chunk agar tetap portable ke SQLite (untuk test) dan
                // tidak membuat query update satu statement yang memuat ratusan
                // baris di HDD yang lambat.
                Attempt::query()
                    ->where('exam_id', $exam->id)
                    ->where('status', AttemptStatus::Locked->value)
                    ->where('lock_reason', 'exam_paused')
                    ->chunkById(
                        (int) config('cbt.scheduler.expire_sweep_chunk', 200),
                        function ($attempts) use ($pauseSeconds) {
                            foreach ($attempts as $attempt) {
                                $newDeadline = $attempt->deadline_at?->copy()->addSeconds($pauseSeconds);
                                $newExpires = $attempt->expires_at?->copy()->addSeconds($pauseSeconds);

                                $attempt->forceFill([
                                    'status' => AttemptStatus::InProgress->value,
                                    'lock_reason' => null,
                                    'locked_at' => null,
                                    'deadline_at' => $newDeadline,
                                    'expires_at' => $newExpires,
                                ])->save();
                            }
                        },
                    );
            }

            $exam->forceFill([
                'status' => ExamStatus::Active,
                'paused_at' => null,
            ])->save();

            $this->audit->log(
                action: 'exam.resumed',
                subject: $exam,
                description: 'Ujian dilanjutkan. Deadline attempt dikompensasi durasi jeda.',
            );

            return $exam;
        });
    }

    /**
     * Tutup ujian TANPA auto submit. Attempt yang belum selesai tetap pending.
     *
     * Dipakai admin bila ingin memberi kesempatan siswa offline mengirim jawaban
     * lewat grace period sebelum dinilai.
     */
    public function closeExam(Exam $exam, User $admin): Exam
    {
        return $this->transitionExamStatus($exam, ExamStatus::Completed, $admin, 'exam.closed');
    }

    /**
     * Tutup ujian DAN submit semua attempt aktif sekaligus.
     *
     * Ini aksi "bom" dari PRD: semua attempt yang belum selesai dinilai
     * berdasarkan jawaban yang sudah masuk. Grace period tidak berlaku.
     */
    public function closeAndAutoSubmitExam(Exam $exam, User $admin): int
    {
        $count = 0;

        DB::transaction(function () use ($exam, $admin, &$count) {
            Attempt::query()
                ->where('exam_id', $exam->id)
                ->whereIn('status', [
                    AttemptStatus::InProgress->value,
                    AttemptStatus::Locked->value,
                    AttemptStatus::Expired->value,
                ])
                // Paksa expires_at = sekarang supaya late sync ditolak total.
                ->update(['expires_at' => now()]);

            $attempts = Attempt::query()
                ->where('exam_id', $exam->id)
                ->whereIn('status', [
                    AttemptStatus::InProgress->value,
                    AttemptStatus::Locked->value,
                    AttemptStatus::Expired->value,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($attempts as $attempt) {
                $this->submit($attempt, SubmitReason::ExamClosed);
                $count++;
            }

            $exam->forceFill(['status' => ExamStatus::Completed])->save();

            $this->audit->log(
                action: 'exam.closed_auto_submit',
                subject: $exam,
                description: "Ujian ditutup admin dengan auto submit {$count} attempt.",
                meta: ['auto_submitted' => $count],
            );
        });

        return $count;
    }

    // ------------------------------------------------------------------
    // Aksi admin per attempt (dari halaman monitoring)
    // ------------------------------------------------------------------

    public function extendAttempt(Attempt $attempt, int $minutes, User $admin): Attempt
    {
        return DB::transaction(function () use ($attempt, $minutes, $admin) {
            $locked = Attempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $newDeadline = ($locked->deadline_at ?? now())->copy()->addMinutes($minutes);

            $locked->forceFill([
                'deadline_at' => $newDeadline,
                'expires_at' => $newDeadline->copy()->addMinutes($locked->exam->graceMinutes()),
                'extended_minutes' => (int) $locked->extended_minutes + $minutes,
                // Jika attempt sudah expired tapi deadline baru belum lewat,
                // kembalikan ke in_progress agar siswa bisa lanjut.
                'status' => now()->lessThanOrEqualTo($newDeadline)
                    ? AttemptStatus::InProgress->value
                    : $locked->status->value,
                'expired_at' => now()->lessThanOrEqualTo($newDeadline) ? null : $locked->expired_at,
            ])->save();

            $this->audit->log(
                action: 'attempt.extended',
                subject: $locked,
                description: "Perpanjangan waktu {$minutes} menit.",
                meta: ['minutes' => $minutes, 'new_deadline' => $locked->deadline_at?->toIso8601String()],
            );

            return $locked;
        });
    }

    public function unlockAttempt(Attempt $attempt, User $admin): Attempt
    {
        return DB::transaction(function () use ($attempt, $admin) {
            $locked = Attempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            // Idempoten: hanya attempt terkunci yang dibuka. Attempt yang sudah
            // berjalan dibiarkan apa adanya agar UI monitoring tidak 500 bila
            // admin menekan "Buka Kunci" dua kali.
            if ($locked->status !== AttemptStatus::Locked) {
                return $locked;
            }

            $locked->forceFill([
                'status' => AttemptStatus::InProgress->value,
                'lock_reason' => null,
                'locked_at' => null,
            ])->save();

            $this->audit->log(
                action: 'attempt.unlocked',
                subject: $locked,
                description: 'Attempt dibuka oleh admin.',
            );

            return $locked;
        });
    }

    public function resetWarnings(Attempt $attempt, User $admin): Attempt
    {
        return DB::transaction(function () use ($attempt, $admin) {
            $locked = Attempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            $previous = (int) $locked->warnings_count;

            $locked->forceFill([
                'warnings_count' => 0,
                'anti_cheat_enforced' => false,
            ])->save();

            $this->audit->log(
                action: 'attempt.warnings_reset',
                subject: $locked,
                description: "Peringatan anti-cheat direset (sebelumnya {$previous}).",
                meta: ['previous_warnings' => $previous],
            );

            return $locked;
        });
    }

    public function forceSubmitAttempt(Attempt $attempt, User $admin): Attempt
    {
        $result = $this->submit($attempt, SubmitReason::AdminForce, $admin->last_login_ip);

        $this->audit->log(
            action: 'attempt.forced_submit',
            subject: $result,
            description: 'Attempt disubmit paksa oleh admin.',
        );

        return $result;
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private function findExistingAttempt(Exam $exam, User $user): ?Attempt
    {
        return Attempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->first();
    }

    private function transitionExamStatus(
        Exam $exam,
        ExamStatus $target,
        User $admin,
        string $auditAction,
    ): Exam {
        return DB::transaction(function () use ($exam, $target, $admin, $auditAction) {
            $before = ['status' => $exam->status->value];

            $exam->forceFill(['status' => $target->value])->save();

            $this->audit->logChanges(
                action: $auditAction,
                subject: $exam,
                before: $before,
                after: ['status' => $target->value],
                description: "Status ujian diubah menjadi {$target->label()}.",
            );

            return $exam;
        });
    }
}

/**
 * Dilempar saat ujian tidak memenuhi syarat untuk diaktifkan.
 *
 * @extends \RuntimeException
 */
class ExamNotReadyException extends \RuntimeException
{
    /**
     * @param  array<int, string>  $blockers
     */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct(implode(' ', $blockers));
    }
}

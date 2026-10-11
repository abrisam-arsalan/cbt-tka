<?php

namespace App\Console\Commands;

use App\Enums\AttemptStatus;
use App\Models\Attempt;
use App\Models\Exam;
use App\Services\AuditLogService;
use App\Services\ScoringService;
use Illuminate\Console\Command;

/**
 * Hitung ulang nilai attempt yang sudah tersubmit memakai aturan penilaian
 * TERBARU (mis. parsial ½ PGK) — karena skor lama tersimpan saat submit.
 *
 * cbt:rescore                 -> semua ujian
 * cbt:rescore --exam=5        -> satu ujian
 * cbt:rescore --dry-run       -> laporkan selisih tanpa menyimpan
 */
class RescoreAttempts extends Command
{
    protected $signature = 'cbt:rescore {--exam= : Hanya attempt pada ujian dengan ID ini} {--dry-run}';

    protected $description = 'Hitung ulang nilai attempt tersubmit dengan aturan penilaian terbaru (aman: idempoten).';

    public function __construct(
        private readonly ScoringService $scoring,
        private readonly AuditLogService $audit,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $attempts = Attempt::query()
            ->where('status', AttemptStatus::Submitted->value)
            ->when($this->option('exam'), fn ($q, $id) => $q->where('exam_id', (int) $id))
            ->orderBy('exam_id')
            ->orderBy('id')
            ->get();

        if ($attempts->isEmpty()) {
            $this->info('Tidak ada attempt tersubmit.');

            return self::SUCCESS;
        }

        $changed = 0;

        foreach ($attempts as $attempt) {
            $old = $attempt->score !== null ? (float) $attempt->score : null;
            $result = $this->scoring->scoreAttempt($attempt, persist: ! $dryRun);
            $new = (float) $result->score;

            if ($old !== null && abs($old - $new) < 0.005) {
                continue;
            }

            $changed++;
            $examTitle = $attempt->exam?->title ?? "#{$attempt->exam_id}";
            $student = $attempt->user?->name ?? "user#{$attempt->user_id}";
            $this->line(sprintf(
                '  %s | %-28s | %s | %6.2f -> %6.2f (poin %s/%d)',
                $dryRun ? 'DRY ' : 'OK  ',
                $examTitle,
                $student,
                $old ?? 0,
                $new,
                rtrim(rtrim(number_format($result->totalPoints, 2, '.', ''), '0'), '.'),
                $result->totalQuestions,
            ));
        }

        if ($changed === 0) {
            $this->info('Semua nilai sudah sesuai aturan terbaru — tidak ada yang berubah.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("{$changed} nilai akan berubah (dry-run, tidak disimpan). Jalankan tanpa --dry-run untuk menerapkan.");

            return self::SUCCESS;
        }

        $this->audit->log(
            action: 'attempts.rescored',
            subject: $this->option('exam') ? Exam::find((int) $this->option('exam')) : null,
            description: "Hitung ulang nilai: {$changed} attempt diperbarui.",
            meta: ['exam_id' => $this->option('exam') ? (int) $this->option('exam') : null, 'changed' => $changed],
        );

        $this->info("Selesai — {$changed} nilai dihitung ulang dan disimpan.");

        return self::SUCCESS;
    }
}

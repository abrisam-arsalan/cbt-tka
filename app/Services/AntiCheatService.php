<?php

namespace App\Services;

use App\Enums\AntiCheatAction;
use App\Enums\AntiCheatEventType;
use App\Enums\AttemptStatus;
use App\Models\AntiCheatLog;
use App\Models\Attempt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penanganan anti-cheat berbasis browser.
 *
 * Alur saat anti_cheat_enabled = true:
 *   1. Browser mengirim laporan kejadian (visibility_hidden, window_blur)
 *   2. Service mencatat ke anti_cheat_logs
 *   3. Bila type menambah warning, counter attempt di-inkremen
 *   4. Bila counter melewati exam.maxWarnings(), tindakan konfigurasi diterapkan
 *      (auto submit atau lock) dan dicatat sebagai action_taken
 *
 * Saat anti_cheat_enabled = false, endpoint tidak mencatat apa pun dan
 * tidak memicu tindakan — sesuai PRD.
 */
class AntiCheatService
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Proses satu laporan kejadian dari browser.
     *
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>   ringkasan untuk balasan JSON
     */
    public function recordEvent(
        Attempt $attempt,
        AntiCheatEventType $type,
        ?string $message = null,
        array $meta = [],
    ): array {
        $exam = $attempt->exam;

        // Bila anti-cheat dimatikan, endpoint tidak melakukan apa-apa.
        // Ini mencegah klien yang dimodifikasi tetap menuliskan log
        // dan membingungkan admin dengan catatan yang tidak bermakna.
        if (! (bool) $exam->anti_cheat_enabled) {
            return [
                'recorded' => false,
                'anti_cheat_enabled' => false,
                'warnings_count' => (int) $attempt->warnings_count,
                'max_warnings' => $exam->maxWarnings(),
                'show_warning_modal' => false,
            ];
        }

        return DB::transaction(function () use ($attempt, $type, $message, $meta) {
            $locked = Attempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();
            $exam = $locked->exam;

            $previousWarnings = (int) $locked->warnings_count;

            $log = AntiCheatLog::create([
                'attempt_id' => $locked->id,
                'type' => $type->value,
                'message' => $message,
                'meta' => $meta === [] ? null : $meta,
                'created_at' => now(),
            ]);

            $newWarnings = $previousWarnings;

            if ($type->incrementsWarning() && $locked->status === AttemptStatus::InProgress) {
                $newWarnings = $previousWarnings + 1;
                $locked->warnings_count = $newWarnings;
            }

            $action = $exam->anti_cheat_action ?? AntiCheatAction::LogOnly;
            $limitExceeded = $newWarnings >= $exam->maxWarnings();

            if ($limitExceeded && ! (bool) $locked->anti_cheat_enforced) {
                AntiCheatLog::create([
                    'attempt_id' => $locked->id,
                    'type' => AntiCheatEventType::LimitExceeded->value,
                    'message' => "Peringatan mencapai batas {$exam->maxWarnings()}.",
                    'created_at' => now(),
                ]);

                if ($action->isEnforcing() && $locked->status !== AttemptStatus::Submitted) {
                    $this->enforce($locked, $action);
                }

                $locked->anti_cheat_enforced = true;
                $locked->save();
            } elseif ($type->incrementsWarning()) {
                $locked->save();
            }

            return [
                'recorded' => true,
                'anti_cheat_enabled' => true,
                'action' => $action->value,
                'action_label' => $action->label(),
                'warnings_count' => (int) $locked->warnings_count,
                'max_warnings' => $exam->maxWarnings(),
                'limit_exceeded' => $limitExceeded,
                'show_warning_modal' => $action->showsWarningModal() && $newWarnings > $previousWarnings,
                'enforced' => $limitExceeded && $action->isEnforcing(),
                'auto_submit' => $limitExceeded
                    && $action === AntiCheatAction::AutoSubmitAfterLimit
                    && $locked->status !== AttemptStatus::Submitted,
                'locked' => $limitExceeded
                    && $action === AntiCheatAction::LockAfterLimit
                    && $locked->status !== AttemptStatus::Submitted,
                'log_id' => $log->id,
            ];
        });
    }

    /**
     * Proses batch kejadian dari browser (saat flush saat kembali online).
     *
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    public function recordBatch(Attempt $attempt, array $events): array
    {
        $summaries = [];

        foreach ($events as $event) {
            $type = AntiCheatEventType::tryFrom((string) ($event['type'] ?? ''));

            if ($type === null) {
                continue;
            }

            $summaries[] = $this->recordEvent(
                attempt: $attempt,
                type: $type,
                message: isset($event['message']) ? (string) $event['message'] : null,
                meta: is_array($event['meta'] ?? null) ? $event['meta'] : [],
            );
        }

        if ($summaries === []) {
            return [
                'processed' => 0,
                'warnings_count' => (int) $attempt->warnings_count,
                'max_warnings' => $attempt->exam->maxWarnings(),
            ];
        }

        $last = end($summaries);

        return [
            'processed' => count($summaries),
            'anti_cheat_enabled' => $last['anti_cheat_enabled'] ?? false,
            'action' => $last['action'] ?? null,
            'warnings_count' => (int) $last['warnings_count'],
            'max_warnings' => (int) $last['max_warnings'],
            'limit_exceeded' => (bool) ($last['limit_exceeded'] ?? false),
            'enforced' => (bool) ($last['enforced'] ?? false),
            'auto_submit' => (bool) ($last['auto_submit'] ?? false),
            'locked' => (bool) ($last['locked'] ?? false),
            'show_warning_modal' => (bool) ($last['show_warning_modal'] ?? false),
        ];
    }

    private function enforce(Attempt $attempt, AntiCheatAction $action): void
    {
        AntiCheatLog::create([
            'attempt_id' => $attempt->id,
            'type' => AntiCheatEventType::ActionTaken->value,
            'message' => "Tindakan {$action->label()} diterapkan.",
            'created_at' => now(),
        ]);

        // Auto-submit dijalankan controller lewat flag auto_submit agar
        // penilaian lewat ExamTimerService (bukan ditangani diam-diam di sini).
        if ($action === AntiCheatAction::LockAfterLimit) {
            $attempt->status = AttemptStatus::Locked;
            $attempt->lock_reason = 'anti_cheat_limit';
            $attempt->locked_at = now();
        }
    }
}

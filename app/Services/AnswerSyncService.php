<?php

namespace App\Services;

use App\Enums\AttemptStatus;
use App\Enums\QuestionType;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use App\Models\User;
use App\Services\Sync\RejectCode;
use App\Services\Sync\SyncItemResult;
use App\Services\Sync\SyncResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Menerima jawaban dari browser siswa dan menegakkan invariant
 * "satu jawaban final per soal per attempt".
 *
 * Desain untuk server HDD dengan 200 siswa concurrent:
 *
 *  - SATU transaksi per request, bukan per jawaban. Pada InnoDB dengan
 *    innodb_flush_log_at_trx_commit=1 setiap commit memicu fsync; 50 item
 *    dalam 50 transaksi berarti 50 fsync. Satu transaksi = satu fsync.
 *
 *  - Baris attempt dikunci di awal transaksi (SELECT ... FOR UPDATE).
 *    Karena satu attempt hanya dipakai satu siswa, lock ini tidak pernah
 *    diperebutkan antar siswa; ia hanya menyeragamkan dua request dari
 *    siswa yang sama (mis. flush outbox yang tumpang tindih).
 *
 *  - Status attempt dibaca ulang DI DALAM transaksi. Tanpa ini, jawaban bisa
 *    masuk setelah scheduler men-submit attempt karena status yang dipakai
 *    adalah hasil baca sebelum transaksi dimulai.
 *
 * Urutan pertahanan:
 *  1. ownership (attempt milik user yang login)
 *  2. status attempt (submitted / locked ditolak)
 *  3. batas waktu (expires_at = deadline + grace period)
 *  4. validasi question milik ujian + payload sesuai tipe soal
 *  5. idempotency_key global
 *  6. client_seq harus lebih baru dari yang tersimpan
 *  7. unique(attempt_id, question_id) di database sebagai pagar terakhir
 */
class AnswerSyncService
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function sync(Attempt $attempt, User $user, array $items): SyncResult
    {
        $now = now();

        // (1) Kepemilikan. Ditegakkan di sini juga, bukan hanya di controller,
        // supaya service ini tidak bisa disalah pakai bila dipanggil dari tempat lain.
        if ((int) $attempt->user_id !== (int) $user->id) {
            return $this->rejectAll($attempt, $items, RejectCode::AttemptNotOwned, $now);
        }

        if ($items === []) {
            return $this->emptyResult($attempt, $now);
        }

        // Deteksi batch cacat lebih awal: question_id atau idempotency_key ganda
        // dalam satu request hampir pasti bug klien, bukan data sah.
        if ($this->hasBatchDuplicates($items)) {
            return $this->rejectAll($attempt, $items, RejectCode::MalformedBatch, $now);
        }

        $questions = $this->loadQuestions($attempt);

        /** @var array<int, SyncItemResult> $results */
        $results = [];

        DB::transaction(function () use ($attempt, $items, $questions, $now, &$results) {
            /** @var Attempt|null $locked */
            $locked = Attempt::query()->whereKey($attempt->id)->lockForUpdate()->first();

            if ($locked === null) {
                $results = $this->buildRejections($items, RejectCode::AttemptNotOwned);

                return;
            }

            // Segarkan state agar keputusan di bawah memakai data terbaru.
            $attempt->setRawAttributes($locked->getAttributes(), true);

            $batchReject = $this->batchLevelRejection($attempt, $now);

            if ($batchReject !== null) {
                $results = $this->buildRejections($items, $batchReject);

                return;
            }

            $late = $this->isLateWindow($attempt, $now);

            foreach ($items as $item) {
                $results[] = $this->applyItem($attempt, $item, $questions, $now, $late);
            }
        });

        return new SyncResult(
            items: $results,
            attemptStatus: $attempt->status->value,
            remainingSeconds: $attempt->remainingSeconds($now),
            serverTime: $now->getTimestampMs(),
            attemptMessage: $this->attemptMessage($attempt, $now),
            deadlinePassed: $attempt->isPastDeadline($now),
            graceEnded: $attempt->isPastGracePeriod($now),
        );
    }

    /**
     * Terapkan satu item jawaban. Dipanggil di dalam transaksi.
     *
     * @param  array<string, mixed>  $item
     * @param  array<int, Question>  $questions
     */
    private function applyItem(
        Attempt $attempt,
        array $item,
        array $questions,
        Carbon $now,
        bool $late,
    ): SyncItemResult {
        $questionId = (int) ($item['question_id'] ?? 0);
        $key = (string) ($item['idempotency_key'] ?? '');
        $clientSeq = (int) ($item['client_seq'] ?? 0);

        // (4a) Soal harus milik ujian ini.
        if (! isset($questions[$questionId])) {
            return SyncItemResult::rejected($questionId, $key, $clientSeq, RejectCode::QuestionNotInExam);
        }

        // (4b) Payload harus sesuai tipe soal dan hanya memuat id milik soal itu.
        $payload = $this->normalizePayload($questions[$questionId], $item['answer_payload'] ?? null);

        if ($payload === null) {
            return SyncItemResult::rejected($questionId, $key, $clientSeq, RejectCode::InvalidPayload);
        }

        // (5) Idempotency global: key yang sama tidak boleh diproses dua kali,
        // walaupun datang dari attempt atau soal yang berbeda.
        if (Answer::query()->where('idempotency_key', $key)->exists()) {
            return SyncItemResult::duplicate($questionId, $key, $clientSeq);
        }

        /** @var Answer|null $existing */
        $existing = Answer::query()
            ->where('attempt_id', $attempt->id)
            ->where('question_id', $questionId)
            ->lockForUpdate()
            ->first();

        if ($existing !== null) {
            // Request lama yang datang terlambat dan ternyata sudah tercatat.
            if ($existing->idempotency_key === $key) {
                return SyncItemResult::duplicate($questionId, $key, $clientSeq);
            }

            // (6) Hanya client_seq yang lebih baru yang boleh menimpa.
            // Ini mencegah flush outbox yang datang tak berurutan mengembalikan
            // jawaban lama di atas jawaban baru.
            if ($clientSeq <= (int) $existing->client_seq) {
                return SyncItemResult::stale($questionId, $key, $clientSeq, (int) $existing->client_seq);
            }

            $existing->forceFill([
                'answer_payload' => $payload,
                'idempotency_key' => $key,
                'client_seq' => $clientSeq,
                // Sekali ditandai terlambat, tetap terlambat — walau siswa
                // memperbaikinya sebelum grace period berakhir.
                'late_sync_flag' => $late || (bool) $existing->late_sync_flag,
            ])->save();

            return SyncItemResult::accepted($questionId, $key, $clientSeq, 'updated', $late);
        }

        try {
            Answer::query()->create([
                'attempt_id' => $attempt->id,
                'question_id' => $questionId,
                'answer_payload' => $payload,
                'idempotency_key' => $key,
                'client_seq' => $clientSeq,
                'late_sync_flag' => $late,
            ]);

            return SyncItemResult::accepted($questionId, $key, $clientSeq, 'created', $late);
        } catch (QueryException $e) {
            // (7) unique(attempt_id, question_id) atau unique(idempotency_key)
            // tersandung request lain yang menang balapan. Baca ulang dan
            // laporkan apa adanya, jangan menimpa diam-diam.
            if ($this->isUniqueViolation($e)) {
                return SyncItemResult::duplicate($questionId, $key, $clientSeq);
            }

            throw $e;
        }
    }

    /**
     * Penolakan yang berlaku untuk seluruh batch, dicek di dalam transaksi.
     */
    private function batchLevelRejection(Attempt $attempt, Carbon $now): ?RejectCode
    {
        return match (true) {
            // (2) Sudah final: nilai terkunci, tidak boleh ada tulisan lagi.
            $attempt->status === AttemptStatus::Submitted => RejectCode::AttemptSubmitted,

            // (2) Terkunci: ujian dijeda admin atau dikunci anti-cheat.
            // Retryable agar outbox siswa tidak kehilangan jawaban.
            $attempt->status === AttemptStatus::Locked => RejectCode::AttemptLocked,

            // (3) Grace period habis: pagar terakhir.
            $attempt->isPastGracePeriod($now) => RejectCode::GracePeriodEnded,

            default => null,
        };
    }

    /**
     * Apakah kita sedang berada di jendela "sudah lewat deadline tapi masih
     * dalam grace period".
     *
     * Di jendela ini attempt berstatus expired (atau in_progress yang belum
     * sempat disapu scheduler) dan jawaban outbox offline masih diterima,
     * ditandai late_sync_flag.
     */
    private function isLateWindow(Attempt $attempt, Carbon $now): bool
    {
        return $attempt->isPastDeadline($now);
    }

    private function attemptMessage(Attempt $attempt, Carbon $now): ?string
    {
        return match (true) {
            $attempt->status === AttemptStatus::Submitted => 'Jawaban sudah dikumpulkan.',
            $attempt->status === AttemptStatus::Locked => 'Ujian sedang dikunci oleh admin.',
            $attempt->isPastGracePeriod($now) => 'Batas akhir pengiriman jawaban sudah lewat.',
            $attempt->isPastDeadline($now) => 'Waktu pengerjaan habis. Jawaban yang tertahan di perangkat sedang dikirim.',
            default => null,
        };
    }

    /**
     * Normalisasi dan validasi payload jawaban terhadap soal.
     *
     * Mengembalikan null bila payload tidak sah. Semua id yang masuk
     * diverifikasi milik soal tersebut, sehingga siswa tidak bisa menyuntik
     * option_id dari soal lain untuk memanipulasi penilaian.
     *
     * @return array<string, mixed>|null
     */
    private function normalizePayload(Question $question, mixed $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        return match ($question->type) {
            QuestionType::Pg => $this->normalizePg($question, $payload),
            QuestionType::Pgk => $this->normalizePgk($question, $payload),
            QuestionType::Boolean => $this->normalizeBoolean($payload),
            QuestionType::Matching => $this->normalizeMatching($question, $payload),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function normalizePg(Question $question, array $payload): ?array
    {
        if (! array_key_exists('option_id', $payload)) {
            return null;
        }

        $optionId = $payload['option_id'];

        // null berarti siswa mengosongkan jawaban.
        if ($optionId === null) {
            return ['option_id' => null];
        }

        if (! is_int($optionId) && ! is_string($optionId)) {
            return null;
        }

        $optionId = (int) $optionId;

        $valid = $question->options->contains(fn ($option) => (int) $option->id === $optionId);

        return $valid ? ['option_id' => $optionId] : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function normalizePgk(Question $question, array $payload): ?array
    {
        if (! array_key_exists('option_ids', $payload)) {
            return null;
        }

        $raw = $payload['option_ids'];

        // Array kosong sah: siswa membatalkan seluruh pilihannya.
        if ($raw === []) {
            return ['option_ids' => []];
        }

        if (! is_array($raw)) {
            return null;
        }

        $validIds = $question->options->map(fn ($option) => (int) $option->id)->all();

        $normalized = [];

        foreach ($raw as $value) {
            if (! is_int($value) && ! is_string($value)) {
                return null;
            }

            $id = (int) $value;

            // Satu id asing saja membuat seluruh payload ditolak.
            if (! in_array($id, $validIds, true)) {
                return null;
            }

            $normalized[] = $id;
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return ['option_ids' => $normalized];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function normalizeBoolean(array $payload): ?array
    {
        if (! array_key_exists('value', $payload)) {
            return null;
        }

        $value = $payload['value'];

        if ($value === null) {
            return ['value' => null];
        }

        if (! is_bool($value)) {
            return null;
        }

        return ['value' => $value];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function normalizeMatching(Question $question, array $payload): ?array
    {
        if (! array_key_exists('mapping', $payload)) {
            return null;
        }

        $raw = $payload['mapping'];

        if ($raw === []) {
            return ['mapping' => []];
        }

        if (! is_array($raw)) {
            return null;
        }

        $validIds = $question->matchingPairs->map(fn ($pair) => (int) $pair->id)->all();

        $mapping = [];

        foreach ($raw as $left => $right) {
            if ((! is_int($left) && ! is_string($left)) || (! is_int($right) && ! is_string($right))) {
                return null;
            }

            $leftId = (int) $left;
            $rightId = (int) $right;

            if (! in_array($leftId, $validIds, true) || ! in_array($rightId, $validIds, true)) {
                return null;
            }

            // Satu item kiri hanya punya satu pasangan: yang terakhir menang.
            $mapping[(string) $leftId] = $rightId;
        }

        return ['mapping' => $mapping];
    }

    /**
     * @return array<int, Question> keyed by question id
     */
    private function loadQuestions(Attempt $attempt): array
    {
        return Question::query()
            ->with(['options', 'matchingPairs'])
            ->where('exam_id', $attempt->exam_id)
            ->get()
            ->keyBy('id')
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function hasBatchDuplicates(array $items): bool
    {
        $seenQuestions = [];
        $seenKeys = [];

        foreach ($items as $item) {
            $questionId = (int) ($item['question_id'] ?? 0);
            $key = (string) ($item['idempotency_key'] ?? '');

            if ($questionId <= 0 || $key === '') {
                return true;
            }

            if (isset($seenQuestions[$questionId]) || isset($seenKeys[$key])) {
                return true;
            }

            $seenQuestions[$questionId] = true;
            $seenKeys[$key] = true;
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, SyncItemResult>
     */
    private function buildRejections(array $items, RejectCode $code): array
    {
        return array_map(
            fn (array $item) => SyncItemResult::rejected(
                (int) ($item['question_id'] ?? 0),
                (string) ($item['idempotency_key'] ?? ''),
                (int) ($item['client_seq'] ?? 0),
                $code,
            ),
            $items,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function rejectAll(Attempt $attempt, array $items, RejectCode $code, Carbon $now): SyncResult
    {
        return new SyncResult(
            items: $items === [] ? [] : $this->buildRejections($items, $code),
            attemptStatus: $attempt->status->value,
            remainingSeconds: $attempt->remainingSeconds($now),
            serverTime: $now->getTimestampMs(),
            attemptMessage: $code->message(),
            deadlinePassed: $attempt->isPastDeadline($now),
            graceEnded: $attempt->isPastGracePeriod($now),
        );
    }

    private function emptyResult(Attempt $attempt, Carbon $now): SyncResult
    {
        return new SyncResult(
            items: [],
            attemptStatus: $attempt->status->value,
            remainingSeconds: $attempt->remainingSeconds($now),
            serverTime: $now->getTimestampMs(),
            attemptMessage: $this->attemptMessage($attempt, $now),
            deadlinePassed: $attempt->isPastDeadline($now),
            graceEnded: $attempt->isPastGracePeriod($now),
        );
    }

    /**
     * Deteksi pelanggaran unique index lintas driver.
     *
     * MySQL/MariaDB memakai kode 1062, SQLite memakai 19 (SQLITE_CONSTRAINT).
     * Keduanya berbagi SQLSTATE 23000.
     */
    private function isUniqueViolation(QueryException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;

        return (string) $exception->getCode() === '23000'
            || in_array($driverCode, [1062, 19, 1555, 2067], true);
    }
}

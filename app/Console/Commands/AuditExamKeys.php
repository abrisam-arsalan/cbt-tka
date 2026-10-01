<?php

namespace App\Console\Commands;

use App\Enums\QuestionType;
use App\Models\Answer;
use App\Models\Exam;
use App\Models\Question;
use Illuminate\Console\Command;

/**
 * cbt:audit-kunci — memeriksa kesehatan kunci jawaban & integritas jawaban
 * siswa sebelum/sesudah ujian, agar tidak ada siswa yang dirugikan:
 *
 *  1) SOAL: kunci tidak valid (pg tanpa/lebih dari satu kunci, boolean tanpa
 *     kunci yang bisa dibaca, menjodohkan tanpa pasangan).
 *  2) JAWABAN YATIM: option_id / pasangan yang direferensikan jawaban siswa
 *     sudah tidak ada di soal — biasanya korban pola lama "edit soal =
 *     hapus-bikin-ulang opsi". Attempt yang punya jawaban yatim berpotensi
 *     dinilai salah walau siswa menjawab benar.
 *
 * Perbaikan: kunci rusak → perbaiki lewat UI (edit kini ID-preserving);
 * jawaban yatim → jalankan ulang lewat monitoring → Aksi → Reset Ujian
 * untuk siswa terdampak.
 */
class AuditExamKeys extends Command
{
    protected $signature = 'cbt:audit-kunci {--exam= : Hanya audit satu ujian dengan ID ini}';

    protected $description = 'Audit kunci jawaban & deteksi jawaban siswa yatim (mencegah siswa dirugikan).';

    public function handle(): int
    {
        $exams = Exam::query()
            ->when($this->option('exam'), fn ($q) => $q->where('id', (int) $this->option('exam')))
            ->orderBy('id')
            ->get();

        if ($exams->isEmpty()) {
            $this->error('Ujian tidak ditemukan.');

            return self::FAILURE;
        }

        $totalKeyIssues = 0;
        $totalOrphans = 0;

        foreach ($exams as $exam) {
            $questions = $exam->questions()
                ->with(['options', 'matchingPairs'])
                ->ordered()
                ->get();

            $this->info("── Ujian #{$exam->id} “{$exam->title}” ({$questions->count()} soal) ──");

            // 1) kunci rusak
            foreach ($questions->where('is_active', true) as $question) {
                $problem = $this->keyProblem($question);

                if ($problem !== null) {
                    $totalKeyIssues++;
                    $this->warn("  ⚠ Kunci  | soal #{$question->order} ({$question->type->value}): {$problem}");
                }
            }

            // 2) jawaban yatim
            $orphansByAttempt = [];

            foreach ($exam->attempts()->with(['user', 'answers.question.options', 'answers.question.matchingPairs'])->get() as $attempt) {
                foreach ($attempt->answers as $answer) {
                    $question = $answer->question;

                    if ($question === null) {
                        continue;
                    }

                    $reason = $this->orphanReason($answer, $question);

                    if ($reason !== null) {
                        $orphansByAttempt[$attempt->id][] = [
                            'name' => $attempt->user?->name ?? "(user #{$attempt->user_id})",
                            'number' => (int) $question->order,
                            'reason' => $reason,
                        ];
                    }
                }
            }

            foreach ($orphansByAttempt as $attemptId => $items) {
                $totalOrphans += count($items);
                $this->error("  ✖ Yatim   | attempt #{$attemptId} — {$items[0]['name']}:");

                foreach ($items as $item) {
                    $this->line("            soal #{$item['number']}: {$item['reason']}");
                }
            }

            if ($totalKeyIssues === 0 && $orphansByAttempt === []) {
                $this->line('  ✔ sehat');
            }
        }

        $this->newLine();

        if ($totalKeyIssues === 0 && $totalOrphans === 0) {
            $this->info('AUDIT BERSIH: semua kunci valid dan tidak ada jawaban yatim.');

            return self::SUCCESS;
        }

        $this->error("Temuan: {$totalKeyIssues} kunci bermasalah, {$totalOrphans} jawaban yatim.");
        $this->line('Tindak lanjut:');
        $this->line('  • Kunci bermasalah → perbaiki lewat menu Bank Soal / Soal Ujian.');
        $this->line('  • Jawaban yatim → siswa terkait sebaiknya mengulang: Monitoring → Aksi → Reset Ujian.');

        return self::SUCCESS;
    }

    private function keyProblem(Question $question): ?string
    {
        if (! $question->hasValidKey()) {
            return match ($question->type) {
                QuestionType::Pg => 'harus tepat satu opsi benar (sekarang '.count($question->correctOptionIds()).')',
                QuestionType::Pgk => 'tidak ada opsi benar',
                QuestionType::Boolean => 'harus tepat satu opsi benar (sekarang '.count($question->correctOptionIds()).')',
                QuestionType::Matching => 'tidak ada pasangan',
            };
        }

        // Boolean dengan opsi berlabel asing (bukan true/false) tidak bisa dinilai.
        if ($question->type === QuestionType::Boolean && $question->booleanKey() === null) {
            return 'label opsi kunci tidak dapat dibaca sebagai true/false';
        }

        return null;
    }

    private function orphanReason(Answer $answer, Question $question): ?string
    {
        return match ($question->type) {
            QuestionType::Pg, QuestionType::Pgk => $this->orphanOptionReason($answer, $question),
            QuestionType::Matching => $this->orphanPairReason($answer, $question),
            QuestionType::Boolean => null,
        };
    }

    private function orphanOptionReason(Answer $answer, Question $question): ?string
    {
        $valid = $question->options->map(fn ($o) => (int) $o->id)->all();

        if ($question->type === QuestionType::Pg) {
            $id = $answer->selectedOptionId();

            return ($id !== null && ! in_array($id, $valid, true))
                ? "opsi terpilih (id {$id}) tidak lagi ada di soal"
                : null;
        }

        $missing = array_values(array_diff($answer->selectedOptionIds(), $valid));

        return $missing === [] ? null : 'opsi terpilih (id '.implode(', ', $missing).') tidak lagi ada';
    }

    private function orphanPairReason(Answer $answer, Question $question): ?string
    {
        $ids = $question->pairIds();

        foreach ($answer->matchingMap() as $left => $right) {
            if (! in_array($left, $ids, true) || ! in_array($right, $ids, true)) {
                return "pasangan {$left}→{$right} tidak lagi ada di soal";
            }
        }

        return null;
    }
}

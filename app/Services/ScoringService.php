<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Question;
use App\Services\Scoring\ScoreResult;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Penilaian otomatis.
 *
 * Aturan penilaian (sesuai PRD):
 *   pg       -> benar bila opsi yang dipilih sama dengan satu-satunya kunci
 *   boolean  -> benar bila nilai sama dengan kunci
 *   pgk      -> himpunan pilihan sama persis dengan kunci = poin penuh (1).
 *               Sebagian benar TANPA satu pun pilihan salah = poin ½.
 *               Menyertak pilihan yang tidak ada di kunci = 0.
 *   matching -> benar HANYA bila seluruh pasangan tepat. Tidak ada nilai sebagian.
 *
 * Soal yang tidak dijawab dihitung ke unanswered_count, bukan wrong_count,
 * agar admin bisa membedakan "siswa tidak tahu" dari "siswa kehabisan waktu".
 * Soal yang dijawab tapi salah dihitung wrong_count.
 */
class ScoringService
{
    /**
     * Nilai dan simpan hasil penilaian sebuah attempt.
     */
    public function scoreAttempt(Attempt $attempt, bool $persist = true): ScoreResult
    {
        $questions = $this->loadQuestions($attempt);
        $answers = $this->loadAnswers($attempt);

        $correct = 0;
        $wrong = 0;
        $unanswered = 0;
        $points = 0.0;
        $perQuestion = [];

        foreach ($questions as $question) {
            $answer = $answers[$question->id] ?? null;
            $earned = 0.0;

            $answered = $answer !== null && ! $answer->isBlank();

            if (! $answered) {
                $unanswered++;
                $isCorrect = false;
            } else {
                // Poin: 1 penuh, ½ parsial (pgk sebagian benar), 0 salah.
                $earned = $this->judgePoints($question, $answer);
                $isCorrect = $earned >= 1.0;

                if ($isCorrect) {
                    $correct++;
                } else {
                    $wrong++;
                }

                $points += $earned;
            }

            $perQuestion[] = $this->describe($question, $answer, $answered, $isCorrect, $earned);
        }

        $total = $questions->count();
        // Skor memakai POIN (bukan jumlah benar) agar parsial ½ pgk terhitung.
        $score = $total > 0 ? round(($points / $total) * 100, 2) : 0.0;

        $result = new ScoreResult(
            totalQuestions: $total,
            correctCount: $correct,
            wrongCount: $wrong,
            unansweredCount: $unanswered,
            score: $score,
            perQuestion: $perQuestion,
            totalPoints: $points,
        );

        if ($persist) {
            $attempt->forceFill([
                'total_questions' => $total,
                'correct_count' => $correct,
                'wrong_count' => $wrong,
                'unanswered_count' => $unanswered,
                'score' => $score,
            ])->save();
        }

        return $result;
    }

    /**
     * Menilai satu jawaban terhadap satu soal.
     */
    public function judge(Question $question, ?Answer $answer): bool
    {
        if ($answer === null || $answer->isBlank()) {
            return false;
        }

        // Soal tanpa kunci yang valid tidak pernah dinilai benar.
        if (! $question->hasValidKey()) {
            return false;
        }

        return match ($question->type) {
            QuestionType::Pg => $this->judgePg($question, $answer),
            QuestionType::Pgk => $this->judgePgk($question, $answer),
            QuestionType::Boolean => $this->judgeBoolean($question, $answer),
            QuestionType::Matching => $this->judgeMatching($question, $answer),
        };
    }

    /**
     * Poin yang diperoleh satu jawaban: 1.0 penuh, 0.5 parsial (pgk), 0.0 salah.
     */
    public function judgePoints(Question $question, ?Answer $answer): float
    {
        if ($answer === null || $answer->isBlank()) {
            return 0.0;
        }

        if (! $question->hasValidKey()) {
            return 0.0;
        }

        if ($question->type === QuestionType::Pgk) {
            return $this->pgkPoints($question, $answer);
        }

        return $this->judge($question, $answer) ? 1.0 : 0.0;
    }

    /**
     * PGK: himpunan sama persis = 1; sebagian benar tanpa menyertakan
     * pilihan di luar kunci = ½; ada satu saja pilihan salah = 0.
     */
    private function pgkPoints(Question $question, Answer $answer): float
    {
        $selected = $answer->selectedOptionIds();
        $key = $question->correctOptionIds();

        if ($selected === [] || $key === []) {
            return 0.0;
        }

        sort($key);

        if ($selected === $key) {
            return 1.0;
        }

        $hasWrong = array_diff($selected, $key) !== [];
        $hasHit = array_intersect($selected, $key) !== [];

        return (! $hasWrong && $hasHit) ? 0.5 : 0.0;
    }

    private function judgePg(Question $question, Answer $answer): bool
    {
        $selected = $answer->selectedOptionId();
        $key = $question->correctOptionIds();

        return $selected !== null && count($key) === 1 && $selected === $key[0];
    }

    /**
     * Pilihan ganda kompleks: perbandingan himpunan, bukan urutan.
     */
    private function judgePgk(Question $question, Answer $answer): bool
    {
        $selected = $answer->selectedOptionIds();
        $key = $question->correctOptionIds();

        if ($selected === [] || $key === []) {
            return false;
        }

        sort($key);

        return $selected === $key;
    }

    private function judgeBoolean(Question $question, Answer $answer): bool
    {
        $selected = $answer->booleanValue();
        $key = $question->booleanKey();

        return $selected !== null && $key !== null && $selected === $key;
    }

    /**
     * Menjodohkan: all-or-nothing.
     *
     * Benar hanya bila jumlah pasangan yang dijawab sama dengan jumlah pasangan
     * soal DAN setiap item kiri dipetakan ke baris pasangannya sendiri.
     */
    private function judgeMatching(Question $question, Answer $answer): bool
    {
        $map = $answer->matchingMap();
        $pairIds = $question->pairIds();

        if ($pairIds === [] || count($map) !== count($pairIds)) {
            return false;
        }

        foreach ($pairIds as $pairId) {
            if (($map[$pairId] ?? null) !== $pairId) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rincian satu soal untuk halaman hasil / pembahasan.
     *
     * @return array<string, mixed>
     */
    private function describe(Question $question, ?Answer $answer, bool $answered, bool $isCorrect, float $points = 0.0): array
    {
        return [
            'question_id' => $question->id,
            'number' => (int) $question->order,
            'type' => $question->type->value,
            'type_label' => $question->type->label(),
            'question_text' => $question->question_text,
            'stimulus' => $question->stimulus,
            'media_url' => $question->media_url,
            'answered' => $answered,
            'is_correct' => $isCorrect,
            'points' => $points,
            'is_partial' => $points > 0 && $points < 1,
            'student_answer' => $this->humanizeAnswer($question, $answer),
            'correct_answer' => $this->humanizeKey($question),
            'options' => $question->type->usesOptions()
                ? $question->options->map(fn ($option) => [
                    'id' => (int) $option->id,
                    'label' => $option->label,
                    'text' => $option->option_text,
                    'is_correct' => (bool) $option->is_correct,
                ])->all()
                : [],
            'pairs' => $question->type === QuestionType::Matching
                ? $question->matchingPairs->map(fn ($pair) => [
                    'id' => (int) $pair->id,
                    'left' => $pair->left_text,
                    'right' => $pair->right_text,
                ])->all()
                : [],
        ];
    }

    /**
     * Ubah payload jawaban siswa menjadi teks yang bisa dibaca manusia.
     */
    private function humanizeAnswer(Question $question, ?Answer $answer): ?string
    {
        if ($answer === null || $answer->isBlank()) {
            return null;
        }

        return match ($question->type) {
            QuestionType::Pg => $this->optionLabel($question, $answer->selectedOptionId()),
            QuestionType::Pgk => implode(', ', array_filter(array_map(
                fn (int $id) => $this->optionLabel($question, $id),
                $answer->selectedOptionIds(),
            ))),
            QuestionType::Boolean => $answer->booleanValue() === true ? 'Benar' : 'Salah',
            QuestionType::Matching => $this->humanizeMatching($question, $answer->matchingMap()),
        };
    }

    private function humanizeKey(Question $question): ?string
    {
        return match ($question->type) {
            QuestionType::Pg, QuestionType::Pgk => implode(', ', array_filter(array_map(
                fn (int $id) => $this->optionLabel($question, $id),
                $question->correctOptionIds(),
            ))),
            QuestionType::Boolean => match ($question->booleanKey()) {
                true => 'Benar',
                false => 'Salah',
                null => null,
            },
            QuestionType::Matching => $question->matchingPairs
                ->map(fn ($pair) => $pair->left_text.' → '.$pair->right_text)
                ->implode('; '),
        };
    }

    private function humanizeMatching(Question $question, array $map): string
    {
        if ($map === []) {
            return '';
        }

        $parts = [];

        foreach ($question->matchingPairs as $pair) {
            $chosenId = $map[$pair->id] ?? null;
            $chosen = $chosenId === null
                ? '(tidak dijawab)'
                : ($question->matchingPairs->firstWhere('id', $chosenId)?->right_text ?? '(tidak valid)');

            $parts[] = $pair->left_text.' → '.$chosen;
        }

        return implode('; ', $parts);
    }

    private function optionLabel(Question $question, ?int $optionId): ?string
    {
        if ($optionId === null) {
            return null;
        }

        $option = $question->options->firstWhere('id', $optionId);

        if ($option === null) {
            return null;
        }

        return $option->label !== null && $option->label !== ''
            ? $option->label
            : $option->option_text;
    }

    /**
     * @return EloquentCollection<int, Question>
     */
    private function loadQuestions(Attempt $attempt): EloquentCollection
    {
        $questions = Question::query()
            ->with(['options', 'matchingPairs'])
            ->where('exam_id', $attempt->exam_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        // Ujian dengan question_count (mis. 30 dari bank 60): nilai HANYA
        // subset soal yang memang ditampilkan ke siswa ini — pakai seed yang
        // sama dengan layar ujian agar daftar identik.
        $exam = $attempt->exam;

        if ($exam !== null && $exam->question_count) {
            $keep = $exam->questionSubsetIds((int) $attempt->shuffle_seed);
            $questions = $questions->filter(fn (Question $q) => in_array((int) $q->id, $keep, true))->values();
        }

        return $questions;
    }

    /**
     * @return array<int, Answer> keyed by question id
     */
    private function loadAnswers(Attempt $attempt): array
    {
        return $attempt->answers()
            ->get()
            ->keyBy('question_id')
            ->all();
    }
}

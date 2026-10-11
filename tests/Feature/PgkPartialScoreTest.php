<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\Option;
use App\Models\Question;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aturan baru PGK: himpunan sama persis = 1 poin; sebagian benar tanpa
 * satu pun pilihan salah = ½ poin; menyertakan pilihan di luar kunci = 0.
 */
class PgkPartialScoreTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: Exam, 1: Question, 2: array<string,int>} */
    private function examWithPgk(?Question $pg = null): array
    {
        $exam = Exam::create(['title' => 'Ujian PGK', 'duration_minutes' => 60, 'status' => ExamStatus::Completed->value]);

        $q = Question::create(['exam_id' => $exam->id, 'type' => 'pgk', 'question_text' => 'Pilih bilangan prima', 'order' => $pg ? 2 : 1, 'is_active' => true]);
        $ids = [];
        foreach ([['A', '2', true], ['B', '3', true], ['C', '4', false], ['D', '5', true]] as [$label, $text, $ok]) {
            $ids[$label] = (int) Option::create(['question_id' => $q->id, 'label' => $label, 'option_text' => $text, 'is_correct' => $ok, 'order' => ord($label) - 65])->id;
        }

        return [$exam, $q, $ids];
    }

    private function attemptWith(Exam $exam, User $student, array $payloadsByQuestion): Attempt
    {
        $attempt = Attempt::create([
            'exam_id' => $exam->id, 'user_id' => $student->id,
            'status' => AttemptStatus::Submitted->value,
            'started_at' => now()->subHour(), 'submitted_at' => now(),
            'deadline_at' => now(), 'expires_at' => now(),
            'shuffle_seed' => 1, 'total_questions' => 1,
        ]);

        $seq = 0;
        foreach ($payloadsByQuestion as $qid => $payload) {
            Answer::create([
                'attempt_id' => $attempt->id, 'question_id' => $qid,
                'answer_payload' => $payload,
                'idempotency_key' => 'pk-'.$attempt->id.'-'.$qid,
                'client_seq' => ++$seq,
            ]);
        }

        return $attempt;
    }

    private function student(): User
    {
        return User::create(['username' => '0010001001', 'name' => 'Siswa', 'password' => 'x', 'role' => UserRole::Siswa->value, 'is_active' => true]);
    }

    public function test_exact_set_gets_full_score(): void
    {
        [$exam, $q, $ids] = $this->examWithPgk();
        $attempt = $this->attemptWith($exam, $this->student(), [$q->id => ['option_ids' => [$ids['A'], $ids['B'], $ids['D']]]]);

        $result = app(ScoringService::class)->scoreAttempt($attempt);

        $this->assertSame(100.0, (float) $result->score);
        $this->assertSame(1, $result->correctCount);
        $this->assertFalse($result->perQuestion[0]['is_partial']);
    }

    public function test_subset_without_wrong_option_gets_half(): void
    {
        [$exam, $q, $ids] = $this->examWithPgk();
        // Kunci {A,B,D}; siswa pilih {A,B} -> sebagian benar, nol salah => ½.
        $attempt = $this->attemptWith($exam, $this->student(), [$q->id => ['option_ids' => [$ids['A'], $ids['B']]]]);

        $result = app(ScoringService::class)->scoreAttempt($attempt);

        $this->assertSame(50.0, (float) $result->score);
        $this->assertSame(0, $result->correctCount, 'Parsial tidak dihitung benar penuh');
        $this->assertTrue($result->perQuestion[0]['is_partial']);
        $this->assertSame(0.5, $result->perQuestion[0]['points']);
    }

    public function test_any_wrong_option_voids_partial(): void
    {
        [$exam, $q, $ids] = $this->examWithPgk();
        // Menyertakan C (bukan kunci) => 0 walau ada A,B benar.
        $attempt = $this->attemptWith($exam, $this->student(), [$q->id => ['option_ids' => [$ids['A'], $ids['B'], $ids['C']]]]);

        $result = app(ScoringService::class)->scoreAttempt($attempt);

        $this->assertSame(0.0, (float) $result->score);
        $this->assertFalse($result->perQuestion[0]['is_partial']);
    }

    public function test_single_correct_option_of_three_key_gets_half(): void
    {
        [$exam, $q, $ids] = $this->examWithPgk();
        $attempt = $this->attemptWith($exam, $this->student(), [$q->id => ['option_ids' => [$ids['D']]]]);

        $result = app(ScoringService::class)->scoreAttempt($attempt);

        $this->assertSame(50.0, (float) $result->score);
    }

    public function test_mixed_exam_averages_points(): void
    {
        // PG benar (1 poin) + PGK parsial (0.5) dari 2 soal => 75.
        $exam = Exam::create(['title' => 'Campuran', 'duration_minutes' => 60, 'status' => ExamStatus::Completed->value]);
        $pg = Question::create(['exam_id' => $exam->id, 'type' => 'pg', 'question_text' => 'Ibu kota RI', 'order' => 1, 'is_active' => true]);
        $pgA = (int) Option::create(['question_id' => $pg->id, 'label' => 'A', 'option_text' => 'Jakarta', 'is_correct' => true, 'order' => 0])->id;
        Option::create(['question_id' => $pg->id, 'label' => 'B', 'option_text' => 'Bogor', 'is_correct' => false, 'order' => 1]);

        [$pgkExam, $pgk, $ids] = $this->examWithPgk($pg);
        // Pindahkan soal pgk ke exam Campuran agar satu ujian dua soal.
        $pgk->update(['exam_id' => $exam->id]);

        $attempt = $this->attemptWith($exam, $this->student(), [
            $pg->id => ['option_id' => $pgA],
            $pgk->id => ['option_ids' => [$ids['A'], $ids['B']]],
        ]);

        $result = app(ScoringService::class)->scoreAttempt($attempt);

        $this->assertSame(75.0, (float) $result->score);
        $this->assertSame(1.5, $result->totalPoints);
    }
}

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

class QuestionCountTest extends TestCase
{
    use RefreshDatabase;

    private function examWithQuestions(int $questionCount, int $totalQuestions): Exam
    {
        $exam = Exam::create([
            'title' => 'Ujian Subset',
            'duration_minutes' => 60,
            'question_count' => $questionCount,
            'status' => ExamStatus::Active->value,
            'start_at' => now()->subMinute(),
            'end_at' => now()->addHour(),
        ]);

        for ($i = 0; $i < $totalQuestions; $i++) {
            $q = Question::create([
                'exam_id' => $exam->id,
                'type' => 'pg',
                'question_text' => "Soal nomor {$i}",
                'order' => $i,
                'is_active' => true,
            ]);
            Option::create(['question_id' => $q->id, 'label' => 'A', 'option_text' => 'benar', 'is_correct' => true, 'order' => 0]);
            Option::create(['question_id' => $q->id, 'label' => 'B', 'option_text' => 'salah', 'is_correct' => false, 'order' => 1]);
        }

        return $exam;
    }

    public function test_subset_is_limited_and_deterministic_per_seed(): void
    {
        $exam = $this->examWithQuestions(3, 6);
        $allIds = Question::where('exam_id', $exam->id)->pluck('id')->all();

        $a = $exam->questionSubsetIds(12345);
        $b = $exam->questionSubsetIds(12345);

        $this->assertCount(3, $a);
        $this->assertSame($a, $b, 'Seed sama harus menghasilkan subset yang sama');
        $this->assertEmpty(array_diff($a, $allIds), 'Subset harus anggota bank soal');
        $this->assertSame(3, $exam->effectiveQuestionCount());
    }

    public function test_scoring_only_grades_the_subset_shown_to_student(): void
    {
        $exam = $this->examWithQuestions(3, 6);
        $user = User::create(['username' => 's1', 'name' => 'Siswa 1', 'password' => 'x', 'role' => UserRole::Siswa->value, 'is_active' => true]);

        $seed = 987654321;
        $subset = $exam->questionSubsetIds($seed);

        $attempt = Attempt::create([
            'exam_id' => $exam->id,
            'user_id' => $user->id,
            'status' => AttemptStatus::InProgress->value,
            'started_at' => now(),
            'deadline_at' => now()->addHour(),
            'expires_at' => now()->addHour(),
            'shuffle_seed' => $seed,
            'total_questions' => 3,
        ]);

        // Siswa menjawab benar HANYA 3 soal subsetnya; 3 soal bank lain tidak pernah dilihat.
        foreach ($subset as $qid) {
            Answer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $qid,
                'answer_payload' => ['option_id' => Option::where('question_id', $qid)->where('is_correct', true)->value('id')],
                'idempotency_key' => 'test-'.$attempt->id.'-'.$qid,
                'client_seq' => 1,
            ]);
        }

        $result = app(ScoringService::class)->scoreAttempt($attempt->fresh());

        $this->assertSame(3, $result->totalQuestions);
        $this->assertSame(3, $result->correctCount);
        $this->assertSame(0, $result->unansweredCount, 'Soal di luar subset tidak boleh dihitung kosong');
        $this->assertSame(100.0, (float) $result->score);
    }
}

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Opsi "Hasil yang Dilihat Siswa": score_only menyembunyikan pembahasan
 * di level PAYLOAD (kunci jawaban tidak pernah dikirim ke browser),
 * full_review menampilkannya seperti biasa.
 */
class ExamResultModeTest extends TestCase
{
    use RefreshDatabase;

    private function build(string $resultMode): array
    {
        $student = User::create(['username' => '0010001001', 'name' => 'Siswa', 'password' => 'x', 'role' => UserRole::Siswa->value, 'is_active' => true]);

        $exam = Exam::create([
            'title' => 'Ujian Mode', 'duration_minutes' => 60,
            'status' => ExamStatus::Completed->value, 'result_mode' => $resultMode,
        ]);

        $q = Question::create(['exam_id' => $exam->id, 'type' => 'pg', 'question_text' => 'Ibu kota RI?', 'order' => 1, 'is_active' => true]);
        $a = (int) Option::create(['question_id' => $q->id, 'label' => 'A', 'option_text' => 'Jakarta', 'is_correct' => true, 'order' => 0])->id;
        Option::create(['question_id' => $q->id, 'label' => 'B', 'option_text' => 'Bandung', 'is_correct' => false, 'order' => 1]);

        $attempt = Attempt::create([
            'exam_id' => $exam->id, 'user_id' => $student->id,
            'status' => AttemptStatus::Submitted->value,
            'score' => 100, 'correct_count' => 1, 'wrong_count' => 0, 'unanswered_count' => 0,
            'total_questions' => 1,
            'started_at' => now()->subHour(), 'submitted_at' => now(),
            'deadline_at' => now(), 'expires_at' => now(), 'shuffle_seed' => 1,
        ]);
        Answer::create(['attempt_id' => $attempt->id, 'question_id' => $q->id, 'answer_payload' => ['option_id' => $a], 'idempotency_key' => 'rm-1', 'client_seq' => 1]);

        return [$student, $attempt];
    }

    public function test_full_review_sends_details(): void
    {
        [$student, $attempt] = $this->build('full_review');

        $this->actingAs($student)
            ->get(route('student.history.show', $attempt))
            ->assertInertia(fn ($page) => $page
                ->where('can_review', true)
                ->has('details', 1)
                ->where('details.0.is_correct', true));
    }

    public function test_score_only_hides_details_from_payload(): void
    {
        [$student, $attempt] = $this->build('score_only');

        $this->actingAs($student)
            ->get(route('student.history.show', $attempt))
            ->assertInertia(fn ($page) => $page
                ->where('can_review', false)
                ->has('details', 0)
                // Nilai tetap tampil.
                ->where('attempt.score', 100));
    }

    public function test_default_is_full_review(): void
    {
        $exam = Exam::create(['title' => 'Default', 'duration_minutes' => 60, 'status' => ExamStatus::Draft->value]);

        $this->assertTrue($exam->showsFullReview());
        $this->assertSame('Nilai + pembahasan', $exam->resultModeLabel());
    }
}

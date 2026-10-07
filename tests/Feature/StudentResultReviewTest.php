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
 * Regresi: halaman hasil/pembahasan siswa pernah menandai SEMUA jawaban
 * dengan 'Salah' (badge pojok kanan) walau opsi yang dipilih hijau benar —
 * karena payload details tidak pernah memuat field is_correct yang dibaca Vue.
 * Kini is_correct dihitung server oleh ScoringService yang sama dengan submit.
 */
class StudentResultReviewTest extends TestCase
{
    use RefreshDatabase;

    private function build(): array
    {
        $student = User::create(['username' => '0010001001', 'name' => 'Siswa', 'password' => 'x', 'role' => UserRole::Siswa->value, 'is_active' => true]);

        $exam = Exam::create(['title' => 'Ujian Review', 'duration_minutes' => 60, 'status' => ExamStatus::Completed->value]);

        $pg = Question::create(['exam_id' => $exam->id, 'type' => 'pg', 'question_text' => 'Ibu kota RI?', 'order' => 1, 'is_active' => true]);
        $pgA = Option::create(['question_id' => $pg->id, 'label' => 'A', 'option_text' => 'Jakarta', 'is_correct' => true, 'order' => 0]);
        Option::create(['question_id' => $pg->id, 'label' => 'B', 'option_text' => 'Bandung', 'is_correct' => false, 'order' => 1]);

        $bool = Question::create(['exam_id' => $exam->id, 'type' => 'boolean', 'question_text' => 'Air mendidih 50C?', 'order' => 2, 'is_active' => true]);
        Option::create(['question_id' => $bool->id, 'label' => 'true', 'option_text' => 'Benar', 'is_correct' => false, 'order' => 0]);
        Option::create(['question_id' => $bool->id, 'label' => 'false', 'option_text' => 'Salah', 'is_correct' => true, 'order' => 1]);

        $attempt = Attempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => AttemptStatus::Submitted->value,
            'score' => 50, 'correct_count' => 1, 'wrong_count' => 1, 'unanswered_count' => 0,
            'total_questions' => 2,
            'started_at' => now()->subHour(), 'submitted_at' => now(),
            'deadline_at' => now(), 'expires_at' => now(),
            'shuffle_seed' => 1,
        ]);

        // PG dijawab BENAR (Jakarta); boolean kunci='false' dijawab SALAH (true).
        Answer::create(['attempt_id' => $attempt->id, 'question_id' => $pg->id, 'answer_payload' => ['option_id' => (int) $pgA->id], 'idempotency_key' => 'rv-1', 'client_seq' => 1]);
        Answer::create(['attempt_id' => $attempt->id, 'question_id' => $bool->id, 'answer_payload' => ['value' => true], 'idempotency_key' => 'rv-2', 'client_seq' => 2]);

        return [$student, $attempt, $pg, $bool];
    }

    public function test_review_payload_carries_correct_is_correct_flags(): void
    {
        [$student, $attempt, $pg, $bool] = $this->build();

        $this->actingAs($student)
            ->get(route('student.history.show', $attempt))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Student/Result')
                ->has('details', 2)
                // Soal 1: dijawab & BENAR (dulu selalu tampil 'Salah').
                ->where('details.0.question_id', (int) $pg->id)
                ->where('details.0.answered', true)
                ->where('details.0.is_correct', true)
                // Soal 2: dijawab & SALAH.
                ->where('details.1.question_id', (int) $bool->id)
                ->where('details.1.answered', true)
                ->where('details.1.is_correct', false)
                ->has('details.0.correct_answer')
                ->has('details.1.student_answer'));
    }

    public function test_boolean_string_false_value_is_recognized(): void
    {
        // Kunci soal boolean adalah 'false'. Jawaban tersimpan sebagai STRING
        // 'false' (varian klien lama) harus dikenali sebagai jawaban BENAR,
        // bukan tertandai 'Tidak dijawab' / 'Salah'.
        [$student, $attempt, $pg, $bool] = $this->build();

        $boolAnswer = Answer::query()->where('attempt_id', $attempt->id)->where('question_id', $bool->id)->first();
        $boolAnswer->answer_payload = ['value' => 'false'];
        $boolAnswer->save();

        $this->actingAs($student)
            ->get(route('student.history.show', $attempt))
            ->assertInertia(fn ($page) => $page
                ->where('details.1.answered', true)
                ->where('details.1.is_correct', true)); // kunci = false, siswa jawab 'false' => benar
    }
}

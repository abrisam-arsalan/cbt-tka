<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\MatchingPair;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionBatch;
use App\Models\User;
use App\Services\ScoringService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jaminan "siswa tidak dirugikan":
 *  - mengedit soal (teks/urutan) TIDAK boleh mengganti ID opsi — jawaban
 *    siswa yang benar harus tetap benar setelah edit (dulu berubah jadi
 *    salah karena opsi dihapus-bikin-ulang);
 *  - salin bank -> ujian deploy kunci dengan benar;
 *  - cbt:audit-kunci menemukan jawaban yatim.
 */
class ScoringKeySafetyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'adm', 'name' => 'Admin', 'password' => 'x', 'role' => UserRole::Admin->value, 'is_active' => true]);
    }

    private function pgQuestion(Exam $exam, int $order = 1): Question
    {
        $q = Question::create(['exam_id' => $exam->id, 'type' => 'pg', 'question_text' => 'Ibu kota RI?', 'order' => $order, 'is_active' => true]);

        $a = Option::create(['question_id' => $q->id, 'label' => 'A', 'option_text' => 'Jakarta', 'is_correct' => true, 'order' => 0]);
        $b = Option::create(['question_id' => $q->id, 'label' => 'B', 'option_text' => 'Bandung', 'is_correct' => false, 'order' => 1]);

        return $q->setRelation('options', $q->options()->get());
    }

    private function attemptWithAnswer(Exam $exam, Question $q, int $optionId): Attempt
    {
        $student = User::create(['username' => '0010001001', 'name' => 'Siswa', 'password' => 'x', 'role' => UserRole::Siswa->value, 'is_active' => true]);

        $attempt = Attempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => AttemptStatus::InProgress->value,
            'started_at' => now(),
            'deadline_at' => now()->addHour(),
            'expires_at' => now()->addHour(),
            'shuffle_seed' => 1,
            'total_questions' => 1,
        ]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $q->id,
            'answer_payload' => ['option_id' => $optionId],
            'idempotency_key' => 'sk-'.$attempt->id.'-'.$q->id,
            'client_seq' => 1,
        ]);

        return $attempt;
    }

    public function test_editing_question_text_keeps_option_ids_and_correct_grading(): void
    {
        $admin = $this->admin();
        $exam = Exam::create(['title' => 'Ujian Edit', 'duration_minutes' => 60, 'status' => ExamStatus::Active->value, 'created_by' => $admin->id]);
        $q = $this->pgQuestion($exam);
        $optionA = $q->options->firstWhere('is_correct', true)->id;

        $attempt = $this->attemptWithAnswer($exam, $q, (int) $optionA);

        // Sebelum edit: dinilai benar.
        $before = app(ScoringService::class)->scoreAttempt($attempt->fresh());
        $this->assertSame(1, $before->correctCount);

        // Admin edit via UI soal ujian: teks diubah, opsi sama (id dikirim).
        $this->actingAs($admin)->put(route('admin.exams.questions.update', ['exam' => $exam, 'question' => $q]), [
            'type' => 'pg',
            'question_text' => 'Ibu kota negara Indonesia adalah ...',
            'order' => (int) $q->order,
            'is_active' => true,
            'options' => [
                ['id' => (int) $optionA, 'label' => 'A', 'option_text' => 'Jakarta', 'is_correct' => true],
                ['id' => (int) $q->options->firstWhere('label', 'B')->id, 'label' => 'B', 'option_text' => 'Surabaya', 'is_correct' => false],
            ],
        ])->assertSessionHasNoErrors();

        // ID tidak berganti.
        $this->assertDatabaseHas('options', ['id' => $optionA, 'question_id' => $q->id, 'option_text' => 'Jakarta']);

        // Jawaban siswa yang sama harus TETAP benar.
        $after = app(ScoringService::class)->scoreAttempt($attempt->fresh());
        $this->assertSame(1, $after->correctCount, 'Edit soal tidak boleh membalah-kan jawaban benar');
    }

    public function test_edit_without_ids_still_preserves_rows_by_label(): void
    {
        // Klien lama / permintaan manual tanpa id: fallback pencokelan label.
        $admin = $this->admin();
        $exam = Exam::create(['title' => 'Ujian Legacy', 'duration_minutes' => 60, 'status' => ExamStatus::Active->value, 'created_by' => $admin->id]);
        $q = $this->pgQuestion($exam);
        $optionA = $q->options->firstWhere('is_correct', true)->id;

        $attempt = $this->attemptWithAnswer($exam, $q, (int) $optionA);

        $this->actingAs($admin)->put(route('admin.exams.questions.update', ['exam' => $exam, 'question' => $q]), [
            'type' => 'pg',
            'question_text' => 'Ubah teks saja',
            'order' => (int) $q->order,
            'is_active' => true,
            'options' => [
                ['label' => 'A', 'option_text' => 'Jakarta', 'is_correct' => true],
                ['label' => 'B', 'option_text' => 'Bandung', 'is_correct' => false],
            ],
        ])->assertSessionHasNoErrors();

        $after = app(ScoringService::class)->scoreAttempt($attempt->fresh());
        $this->assertSame(1, $after->correctCount);
    }

    public function test_bank_copy_deploys_keys_to_exam(): void
    {
        $admin = $this->admin();
        $class = \App\Models\SchoolClass::create(['name' => '7A', 'grade' => 7, 'is_active' => true]);

        $batch = QuestionBatch::create(['name' => 'Bank Uji', 'class_id' => $class->id, 'created_by' => $admin->id]);

        $bank = Question::create(['batch_id' => $batch->id, 'type' => 'pg', 'question_text' => '1+1?', 'order' => 1, 'is_active' => true]);
        Option::create(['question_id' => $bank->id, 'label' => 'A', 'option_text' => '1', 'is_correct' => false, 'order' => 0]);
        Option::create(['question_id' => $bank->id, 'label' => 'B', 'option_text' => '2', 'is_correct' => true, 'order' => 1]);

        $exam = Exam::create(['title' => 'Ujian Copy', 'class_id' => $class->id, 'duration_minutes' => 30, 'status' => ExamStatus::Draft->value, 'created_by' => $admin->id]);

        app(\App\Services\QuestionCopyService::class)->copyBatchesToExam([$batch->id], $exam);

        $copy = $exam->questions()->first();
        $this->assertNotNull($copy);
        $this->assertTrue($copy->hasValidKey(), 'Salinan soal ujian wajib punya kunci valid');

        $key = $copy->correctOptionIds();
        $this->assertCount(1, $key);
        $this->assertSame('B', $copy->options->find($key[0])->label);
    }

    public function test_audit_command_flags_orphaned_answer(): void
    {
        $admin = $this->admin();
        $exam = Exam::create(['title' => 'Ujian Yatim', 'duration_minutes' => 60, 'status' => ExamStatus::Active->value, 'created_by' => $admin->id]);
        $q = $this->pgQuestion($exam);

        // Jawaban menunjuk opsi yang tidak pernah ada (korban pola lama).
        $this->attemptWithAnswer($exam, $q, 999999);

        $this->actingAs($admin);
        $this->artisan('cbt:audit-kunci')
            ->expectsOutputToContain('tidak lagi ada')
            ->assertExitCode(0);
    }

    public function test_matching_edit_preserves_pair_ids(): void
    {
        $admin = $this->admin();
        $exam = Exam::create(['title' => 'Ujian Matching', 'duration_minutes' => 60, 'status' => ExamStatus::Active->value, 'created_by' => $admin->id]);

        $q = Question::create(['exam_id' => $exam->id, 'type' => 'matching', 'question_text' => 'Jodohkan', 'order' => 1, 'is_active' => true]);
        $p1 = MatchingPair::create(['question_id' => $q->id, 'left_text' => 'RI', 'right_text' => 'Indonesia', 'order' => 0]);
        $p2 = MatchingPair::create(['question_id' => $q->id, 'left_text' => 'JKT', 'right_text' => 'Jakarta', 'order' => 1]);

        $student = User::create(['username' => '0010001002', 'name' => 'Siswa 2', 'password' => 'x', 'role' => UserRole::Siswa->value, 'is_active' => true]);
        $attempt = Attempt::create([
            'exam_id' => $exam->id, 'user_id' => $student->id,
            'status' => AttemptStatus::InProgress->value,
            'started_at' => now(), 'deadline_at' => now()->addHour(), 'expires_at' => now()->addHour(),
            'shuffle_seed' => 1, 'total_questions' => 1,
        ]);
        Answer::create([
            'attempt_id' => $attempt->id, 'question_id' => $q->id,
            'answer_payload' => ['mapping' => [(int) $p1->id => (int) $p1->id, (int) $p2->id => (int) $p2->id]],
            'idempotency_key' => 'sk-m-'.$attempt->id, 'client_seq' => 1,
        ]);

        $this->assertSame(1, app(ScoringService::class)->scoreAttempt($attempt)->correctCount);

        $this->actingAs($admin)->put(route('admin.exams.questions.update', ['exam' => $exam, 'question' => $q]), [
            'type' => 'matching',
            'question_text' => 'Jodohkan (direvisi)',
            'order' => 1,
            'is_active' => true,
            'matching_pairs' => [
                ['id' => (int) $p1->id, 'left_text' => 'RI', 'right_text' => 'Negara Indonesia'],
                ['id' => (int) $p2->id, 'left_text' => 'JKT', 'right_text' => 'Jakarta'],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, app(ScoringService::class)->scoreAttempt($attempt->fresh())->correctCount);
    }
}

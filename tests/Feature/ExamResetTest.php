<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Answer;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reset ujian (force majeure) dari halaman monitoring: attempt & jawabannya
 * terhapus, kepesertaan tetap, siswa bisa mengerjakan ulang.
 */
class ExamResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_reset_submitted_attempt(): void
    {
        $admin = User::create(['username' => 'adm', 'name' => 'Admin', 'password' => 'x', 'role' => UserRole::Admin->value, 'is_active' => true]);
        $student = User::create(['username' => '0010001001', 'name' => 'Siswa 1', 'password' => 'x', 'role' => UserRole::Siswa->value, 'is_active' => true]);

        $exam = Exam::create([
            'title' => 'Ujian Reset',
            'duration_minutes' => 60,
            'status' => ExamStatus::Active->value,
            'created_by' => $admin->id,
            'require_fullscreen' => true,
        ]);

        $question = Question::create([
            'exam_id' => $exam->id,
            'type' => 'pg',
            'question_text' => 'Soal 1',
            'order' => 0,
            'is_active' => true,
        ]);

        ExamParticipant::create(['exam_id' => $exam->id, 'user_id' => $student->id, 'is_active' => true]);

        $attempt = Attempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => AttemptStatus::Submitted->value,
            'started_at' => now()->subMinutes(10),
            'submitted_at' => now(),
            'deadline_at' => now()->addMinutes(50),
            'expires_at' => now()->addMinutes(60),
            'shuffle_seed' => 12345,
            'total_questions' => 1,
        ]);

        Answer::create([
            'attempt_id' => $attempt->id,
            'question_id' => $question->id,
            'answer_payload' => ['value' => 'A'],
            'idempotency_key' => 'reset-test-1',
            'client_seq' => 1,
        ]);

        $this->assertSame(1, $attempt->answers()->count());

        $response = $this->actingAs($admin)
            ->from(route('admin.exams.monitoring.index', $exam))
            ->post(route('admin.exams.monitoring.reset', ['exam' => $exam, 'attempt' => $attempt]));

        $response->assertRedirect(route('admin.exams.monitoring.index', $exam));

        // Attempt & jawaban terhapus; peserta tetap terdaftar => bisa ikut lagi.
        $this->assertDatabaseMissing('attempts', ['id' => $attempt->id]);
        $this->assertDatabaseMissing('answers', ['attempt_id' => $attempt->id]);
        $this->assertDatabaseHas('exam_participants', ['exam_id' => $exam->id, 'user_id' => $student->id]);

        // Jejak audit tersimpan + kolom fullscreen tersedia di payload siswa.
        $this->assertDatabaseHas('audit_logs', ['action' => 'attempt.reset']);
        $this->assertTrue((bool) $exam->fresh()->require_fullscreen);
    }
}

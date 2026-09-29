<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Halaman /admin/monitoring kini satu format real-time (komponen yang sama
 * dengan monitoring per-ujian): baris membawa exam_id/exam_title supaya
 * menu Aksi (termasuk Reset Ujian) bekerja lintas ujian.
 */
class MonitoringMergeTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_monitoring_renders_realtime_component_with_per_row_exam_context(): void
    {
        $admin = User::create(['username' => 'adm', 'name' => 'Admin', 'password' => 'x', 'role' => UserRole::Admin->value, 'is_active' => true]);
        $student = User::create(['username' => '0010001001', 'name' => 'Siswa 1', 'password' => 'x', 'role' => UserRole::Siswa->value, 'is_active' => true]);

        $exam = Exam::create([
            'title' => 'Ujian Gabung',
            'duration_minutes' => 60,
            'status' => ExamStatus::Active->value,
            'created_by' => $admin->id,
        ]);

        Attempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => AttemptStatus::InProgress->value,
            'started_at' => now()->subMinute(),
            'deadline_at' => now()->addHour(),
            'expires_at' => now()->addHour(),
            'shuffle_seed' => 7,
            'total_questions' => 10,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.monitoring.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Monitoring/Index')
                ->where('exam', null)
                ->has('rows', 1)
                ->where('rows.0.exam_id', $exam->id)
                ->where('rows.0.exam_title', 'Ujian Gabung')
                ->has('session_tokens', 1)
                ->has('session_tokens.0.token')
                ->has('exam_options', 1));
    }

    public function test_per_exam_monitoring_still_works_with_same_component(): void
    {
        $admin = User::create(['username' => 'adm2', 'name' => 'Admin 2', 'password' => 'x', 'role' => UserRole::Admin->value, 'is_active' => true]);

        $exam = Exam::create([
            'title' => 'Ujian Per Kelas',
            'duration_minutes' => 60,
            'status' => ExamStatus::Active->value,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.exams.monitoring.index', $exam))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Monitoring/Index')
                ->where('exam.id', $exam->id)
                ->has('session_token.token'));
    }
}

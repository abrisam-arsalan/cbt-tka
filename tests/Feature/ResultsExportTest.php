<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menu Hasil Ujian: filter + tombol Cetak (halaman A4) + Unduh Excel (.xlsx).
 */
class ResultsExportTest extends TestCase
{
    use RefreshDatabase;

    private function makeData(): array
    {
        $admin = User::create(['username' => 'adm', 'name' => 'Admin', 'password' => 'x', 'role' => UserRole::Admin->value, 'is_active' => true]);
        $class = SchoolClass::create(['name' => '7A', 'grade' => 7, 'is_active' => true]);
        $student = User::create(['username' => '0010001001', 'name' => 'Siswa 1', 'password' => 'x', 'role' => UserRole::Siswa->value, 'is_active' => true, 'class_id' => $class->id]);

        $exam = Exam::create([
            'title' => 'Ujian Akhir',
            'duration_minutes' => 60,
            'status' => ExamStatus::Completed->value,
            'created_by' => $admin->id,
        ]);

        Attempt::create([
            'exam_id' => $exam->id,
            'user_id' => $student->id,
            'status' => AttemptStatus::Submitted->value,
            'score' => 87.5,
            'correct_count' => 7,
            'wrong_count' => 2,
            'unanswered_count' => 1,
            'total_questions' => 10,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
            'deadline_at' => now(),
            'expires_at' => now(),
            'shuffle_seed' => 1,
        ]);

        // Sejak hasil per-ujian berbasis ROSTER PESERTA, siswa harus terdaftar
        // sebagai peserta agar attempt-nya muncul di daftar hasil/cetak/unduh.
        $exam->participants()->create(['user_id' => $student->id, 'is_active' => true]);

        return [$admin, $exam, $class];
    }

    public function test_results_index_accepts_exam_and_class_filters(): void
    {
        [$admin, $exam, $class] = $this->makeData();

        $this->actingAs($admin)
            ->get(route('admin.results.index', ['exam' => $exam->id, 'kelas' => $class->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('filters.exam', $exam->id)
                ->has('rows', 1)
                ->where('rows.0.score', 87.5));

        // Filter kelas lain => kosong.
        $other = SchoolClass::create(['name' => '8A', 'grade' => 8, 'is_active' => true]);
        $this->actingAs($admin)
            ->get(route('admin.results.index', ['kelas' => $other->id]))
            ->assertInertia(fn ($page) => $page->has('rows', 0));
    }

    public function test_print_page_renders_rows(): void
    {
        [$admin, $exam] = $this->makeData();

        $this->actingAs($admin)
            ->get(route('admin.results.print', ['exam' => $exam->id]))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Results/Print')
                ->where('exam_title', 'Ujian Akhir')
                ->has('rows', 1));
    }

    public function test_download_returns_xlsx_file(): void
    {
        [$admin, $exam] = $this->makeData();

        $response = $this->actingAs($admin)
            ->get(route('admin.results.export', ['exam' => $exam->id]));

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type') ?? '');
        $this->assertStringContainsString('xlsx', strtolower($response->headers->get('Content-Disposition') ?? ''));

        // Magic bytes XLSX (ZIP) => "PK".
        $this->assertSame('PK', substr($response->streamedContent(), 0, 2));
    }
}

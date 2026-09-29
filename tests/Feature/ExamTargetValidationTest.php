<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regresi: form ujian selalu mengirim class_id & grade (yang tidak dipakai
 * bernilai kosong). Aturan lama (sometimes tanpa nullable + exclude_with)
 * membuat EDIT ujian gagal validasi "validation.integer / validation.string"
 * di bawah Target Peserta.
 */
class ExamTargetValidationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'adm', 'name' => 'Admin', 'password' => 'x', 'role' => UserRole::Admin->value, 'is_active' => true]);
    }

    /** Payload seperti yang dikirim Form.vue (semua key selalu ada). */
    private function payload(array $override = []): array
    {
        return array_merge([
            'title' => 'Ujian Edit',
            'description' => '',
            'class_id' => '',
            'grade' => '',
            'duration_minutes' => 90,
            'question_count' => '',
            'start_at' => now()->format('Y-m-d\TH:i'),
            'end_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'anti_cheat_enabled' => true,
            'require_fullscreen' => false,
            'anti_cheat_max_warnings' => 3,
            'anti_cheat_action' => 'log_only',
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'offline_grace_minutes' => 10,
        ], $override);
    }

    public function test_store_keeps_class_target_despite_empty_grade(): void
    {
        $class = SchoolClass::create(['name' => '7B', 'grade' => 7, 'is_active' => true]);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.exams.store'), $this->payload(['class_id' => $class->id]));

        $response->assertSessionHasNoErrors();

        $exam = Exam::firstWhere('title', 'Ujian Edit');
        $this->assertSame($class->id, (int) $exam->class_id);
        $this->assertNull($exam->grade);
    }

    public function test_store_keeps_grade_target_despite_empty_class(): void
    {
        $response = $this->actingAs($this->admin())
            ->post(route('admin.exams.store'), $this->payload(['grade' => '8']));

        $response->assertSessionHasNoErrors();

        $exam = Exam::firstWhere('title', 'Ujian Edit');
        $this->assertSame('8', (string) $exam->grade);
        $this->assertNull($exam->class_id);
    }

    public function test_update_with_empty_targets_passes_validation(): void
    {
        $exam = Exam::create([
            'title' => 'Ujian Aktif',
            'duration_minutes' => 60,
            'status' => ExamStatus::Active->value,
        ]);

        // Kasus pengguna: ubah tanggal selesai saja, target "Semua siswa".
        $response = $this->actingAs($this->admin())
            ->put(route('admin.exams.update', $exam), $this->payload([
                'title' => 'Ujian Aktif',
                'end_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.exams.show', $exam));

        $fresh = $exam->fresh();
        $this->assertNull($fresh->class_id);
        $this->assertNull($fresh->grade);
        $this->assertNotNull($fresh->end_at);
    }

    public function test_update_can_switch_target_from_rombel_to_all(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => '9A', 'grade' => 9, 'is_active' => true]);

        $exam = Exam::create([
            'title' => 'Ujian Rombel',
            'class_id' => $class->id,
            'duration_minutes' => 60,
            'status' => ExamStatus::Active->value,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.exams.update', $exam), $this->payload(['title' => 'Ujian Rombel']))
            ->assertSessionHasNoErrors();

        $fresh = $exam->fresh();
        $this->assertNull($fresh->class_id, 'Target harus ikut terkosongkan saat pindah ke Semua siswa.');
    }
}

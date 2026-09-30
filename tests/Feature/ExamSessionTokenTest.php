<?php

namespace Tests\Feature;

use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\ExamSessionTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Token kini SATU per ujian, tidak berbasis waktu lagi (pernyataan user).
 * Dulu rotasi 30 menit membuat sebagian siswa "gagal masukkan token walau
 * sudah benar" saat token berganti ketika mereka sedang mengetik.
 */
class ExamSessionTokenTest extends TestCase
{
    use RefreshDatabase;

    private function studentIn(?Exam $exam = null): User
    {
        $class = SchoolClass::create(['name' => '7A', 'grade' => 7, 'is_active' => true]);

        return User::create([
            'username' => '0010001001',
            'name' => 'Siswa 1',
            'password' => 'x',
            'role' => UserRole::Siswa->value,
            'is_active' => true,
            'class_id' => $class->id,
        ]);
    }

    public function test_token_is_stable_and_does_not_change_over_time(): void
    {
        $tokens = app(ExamSessionTokenService::class);
        $exam = Exam::create([
            'title' => 'Ujian Token',
            'duration_minutes' => 60,
            'status' => ExamStatus::Active->value,
        ]);

        $first = $tokens->current($exam)['token'];

        // Waktu bergeser jauh (2 jam) — token tetap sama karena berbasis ujian.
        $this->travel(2)->hours();
        $exam->refresh();
        $second = $tokens->current($exam)['token'];

        $this->assertSame($first, $second);

        // Token lama masih valid diverifikasi walau waktu sudah jauh berganti.
        $this->assertTrue($tokens->verify($exam->fresh(), $first));
    }

    public function test_verify_accepts_lowercase_and_dashes_regardless(): void
    {
        $tokens = app(ExamSessionTokenService::class);
        $exam = Exam::create([
            'title' => 'Ujian Normalisasi',
            'duration_minutes' => 60,
            'status' => ExamStatus::Active->value,
        ]);

        $raw = $tokens->current($exam)['token']; // mis. "ABCD-EFGH"
        $messy = strtolower(str_replace('-', '', $raw)).' ';

        $this->assertTrue($tokens->verify($exam->fresh(), $messy));
        $this->assertFalse($tokens->verify($exam->fresh(), 'ZZZZ-ZZZZ'));
    }

    public function test_manual_rotation_keeps_previous_token_working(): void
    {
        $tokens = app(ExamSessionTokenService::class);
        $exam = Exam::create([
            'title' => 'Ujian Rotasi',
            'duration_minutes' => 60,
            'status' => ExamStatus::Active->value,
        ]);

        $old = $tokens->current($exam)['token'];
        $exam->refresh();

        $new = $tokens->rotate($exam);
        $this->assertNotSame($old, $new);

        // Token baru valid; token lama masih diterima (transisi, grace satu langkah).
        $this->assertTrue($tokens->verify($exam->fresh(), $new));
        $this->assertTrue($tokens->verify($exam->fresh(), $old));
    }

    public function test_student_can_join_with_stable_token(): void
    {
        $student = $this->studentIn();
        $exam = Exam::create([
            'title' => 'Ujian Join',
            'duration_minutes' => 60,
            'status' => ExamStatus::Active->value,
            'start_at' => now()->subMinute(),
            'end_at' => now()->addHour(),
        ]);

        $exam->participants()->create(['user_id' => $student->id, 'is_active' => true]);
        $token = app(ExamSessionTokenService::class)->current($exam)['token'];

        // huruf kecil & tanpa strip — meniru siswa mengetik di HP
        $sloppy = strtolower(str_replace('-', '', $token));

        $this->actingAs($student)
            ->post(route('student.exam.join.attempt', $exam), ['token' => $sloppy])
            ->assertSessionHasNoErrors();
    }
}

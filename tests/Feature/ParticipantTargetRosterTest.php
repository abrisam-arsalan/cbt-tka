<?php

namespace Tests\Feature;

use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\Exam;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\PresenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Empat permintaan sekaligus:
 *  1) target jenjang/rombel => peserta ter-deploy otomatis;
 *  2) ujian terkunci target => siswa/kelas lain tidak bisa ditambahkan;
 *  3) monitoring menampilkan peserta 'Belum Login';
 *  4) hasil (filter per ujian) menyertakan 'Belum mengikuti ujian'.
 */
class ParticipantTargetRosterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'adm', 'name' => 'Admin', 'password' => 'x', 'role' => UserRole::Admin->value, 'is_active' => true]);
    }

    private function student(string $username, SchoolClass $class): User
    {
        return User::create([
            'username' => $username, 'name' => 'Siswa '.$username, 'password' => 'x',
            'role' => UserRole::Siswa->value, 'is_active' => true, 'class_id' => $class->id,
        ]);
    }

    public function test_store_exam_auto_deploys_participants_by_grade(): void
    {
        $c9 = SchoolClass::create(['name' => '9A', 'grade' => 9, 'is_active' => true]);
        $c7 = SchoolClass::create(['name' => '7A', 'grade' => 7, 'is_active' => true]);
        $s1 = $this->student('0010009001', $c9);
        $s2 = $this->student('0010009002', $c9);
        $outside = $this->student('0010007001', $c7);

        $this->actingAs($this->admin())
            ->post(route('admin.exams.store'), [
                'title' => 'Ujian Kelas 9',
                'grade' => '9',
                'class_id' => '',
                'duration_minutes' => 60,
                'batch_ids' => [],
            ])
            ->assertSessionHasNoErrors();

        $exam = Exam::firstWhere('title', 'Ujian Kelas 9');
        $ids = $exam->participants()->pluck('user_id')->map(fn ($i) => (int) $i)->sort()->values()->all();

        $this->assertEquals([$s1->id, $s2->id], $ids, 'Hanya siswa jenjang 9 yang ter-deploy');
        $this->assertNotContains($outside->id, $ids);
    }

    public function test_locked_exam_rejects_other_grade_bulk_and_single(): void
    {
        $admin = $this->admin();
        $c9 = SchoolClass::create(['name' => '9B', 'grade' => 9, 'is_active' => true]);
        $c7 = SchoolClass::create(['name' => '7A', 'grade' => 7, 'is_active' => true]);
        $alien = $this->student('0010007009', $c7);

        $exam = Exam::create([
            'title' => 'Ujian Terkunci 9',
            'grade' => '9',
            'duration_minutes' => 60,
            'status' => ExamStatus::Draft->value,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.exams.participants.index', $exam))
            ->post(route('admin.exams.participants.bulk', $exam), ['class_id' => $c7->id])
            ->assertRedirect(route('admin.exams.participants.index', $exam))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->post(route('admin.exams.participants.store', $exam), ['user_id' => $alien->id])
            ->assertSessionHas('error');

        $this->assertSame(0, $exam->participants()->count(), 'Tidak boleh ada peserta di luar jenjang');
    }

    public function test_target_change_resyncs_but_keeps_participants_with_attempts(): void
    {
        $admin = $this->admin();
        $c9 = SchoolClass::create(['name' => '9C', 'grade' => 9, 'is_active' => true]);
        $c7 = SchoolClass::create(['name' => '7B', 'grade' => 7, 'is_active' => true]);
        $s9a = $this->student('0010009101', $c9);
        $s9b = $this->student('0010009102', $c9);
        $s7 = $this->student('0010007101', $c7);

        $exam = Exam::create([
            'title' => 'Ujian Pindah Target',
            'grade' => '9',
            'duration_minutes' => 60,
            'status' => ExamStatus::Active->value,
            'created_by' => $admin->id,
        ]);
        $exam->participants()->create(['user_id' => $s9a->id, 'is_active' => true]);
        $exam->participants()->create(['user_id' => $s9b->id, 'is_active' => true]);

        // s9a sudah mulai mengerjakan -> tidak boleh hilang saat target berganti.
        Attempt::create([
            'exam_id' => $exam->id, 'user_id' => $s9a->id,
            'status' => AttemptStatus::InProgress->value,
            'started_at' => now(), 'deadline_at' => now()->addHour(), 'expires_at' => now()->addHour(),
            'shuffle_seed' => 1, 'total_questions' => 0,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.exams.update', $exam), [
                'title' => 'Ujian Pindah Target',
                'grade' => '7',
                'class_id' => '',
                'duration_minutes' => 60,
            ])
            ->assertSessionHasNoErrors();

        $ids = $exam->participants()->pluck('user_id')->map(fn ($i) => (int) $i)->sort()->values()->all();

        $this->assertEquals([$s9a->id, $s7->id], $ids);
    }

    public function test_monitoring_rows_include_belum_login_participants(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => '8A', 'grade' => 8, 'is_active' => true]);
        $took = $this->student('0010008001', $class);
        $absent = $this->student('0010008002', $class);

        $exam = Exam::create([
            'title' => 'Ujian Roster', 'class_id' => $class->id, 'duration_minutes' => 60,
            'status' => ExamStatus::Active->value, 'created_by' => $admin->id,
        ]);

        // Peserta terdaftar (auto-deploy tidak terjadi karena exam dibuat langsung).
        $exam->participants()->create(['user_id' => $took->id, 'is_active' => true]);
        $exam->participants()->create(['user_id' => $absent->id, 'is_active' => true]);

        Attempt::create([
            'exam_id' => $exam->id, 'user_id' => $took->id,
            'status' => AttemptStatus::InProgress->value,
            'started_at' => now(), 'deadline_at' => now()->addHour(), 'expires_at' => now()->addHour(),
            'shuffle_seed' => 1, 'total_questions' => 10,
        ]);

        $rows = app(PresenceService::class)->monitoringRows($exam);
        $summary = app(PresenceService::class)->summarize($rows);

        $this->assertCount(2, $rows);
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['belum_login']);

        $belumLogin = collect($rows)->firstWhere('status', 'belum_login');
        $this->assertNotNull($belumLogin);
        $this->assertNull($belumLogin['attempt_id']);
        $this->assertSame($absent->id, (int) $belumLogin['user_id']);

        // Halaman monitoring juga menyajikan barisnya.
        $this->actingAs($admin)
            ->get(route('admin.exams.monitoring.index', $exam))
            ->assertInertia(fn ($page) => $page->has('rows', 2));
    }

    public function test_results_roster_marks_those_who_did_not_take_exam(): void
    {
        $admin = $this->admin();
        $class = SchoolClass::create(['name' => '9D', 'grade' => 9, 'is_active' => true]);
        $done = $this->student('0010009201', $class);
        $absent = $this->student('0010009202', $class);

        $exam = Exam::create([
            'title' => 'Ujian Nilai', 'grade' => '9', 'duration_minutes' => 60,
            'status' => ExamStatus::Completed->value, 'created_by' => $admin->id,
        ]);

        // Dua peserta terdaftar: satu mengerjakan & submit, satu tidak muncul.
        $exam->participants()->create(['user_id' => $done->id, 'is_active' => true]);
        $exam->participants()->create(['user_id' => $absent->id, 'is_active' => true]);

        Attempt::create([
            'exam_id' => $exam->id, 'user_id' => $done->id,
            'status' => AttemptStatus::Submitted->value,
            'score' => 90, 'correct_count' => 9, 'wrong_count' => 1, 'unanswered_count' => 0,
            'total_questions' => 10, 'started_at' => now()->subHour(),
            'submitted_at' => now(), 'deadline_at' => now(), 'expires_at' => now(),
            'shuffle_seed' => 1,
        ]);

        // Peserta s2 TIDAK punya attempt -> harus tetap tampil sebagai 'Belum mengikuti ujian'.
        // Roster ter-sort (kelas, nama): [0]=Siswa...01 (selesai), [1]=Siswa...02 (belum ikut).
        $this->actingAs($admin)
            ->get(route('admin.results.index', ['exam' => $exam->id]))
            ->assertInertia(fn ($page) => $page
                ->has('rows', 2)
                ->where('rows.0.participated', true)
                ->where('rows.0.score', 90)
                ->where('rows.1.participated', false)
                ->where('rows.1.note', 'Belum mengikuti ujian')
                ->where('rows.1.name', 'Siswa 0010009202'));

        // Halaman cetak ikut menampilkan keduanya.
        $this->actingAs($admin)
            ->get(route('admin.results.print', ['exam' => $exam->id]))
            ->assertInertia(fn ($page) => $page->has('rows', 2));

        // Unduhan xlsx tetap sukses dengan roster lengkap.
        $this->actingAs($admin)
            ->get(route('admin.results.export', ['exam' => $exam->id]))
            ->assertOk();
    }
}

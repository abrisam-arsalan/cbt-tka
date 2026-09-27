<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ParticipantController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
    ) {}

    public function index(Exam $exam): Response
    {
        $participants = $exam->participants()
            ->with('user.schoolClass')
            ->orderBy('id')
            ->get();

        $participantIds = $participants->pluck('user_id')->all();

        $availableUsers = User::query()
            ->siswa()
            ->active()
            ->whereNotIn('id', $participantIds)
            ->with('schoolClass')
            ->orderBy('name')
            ->get();

        // Jumlah siswa aktif per kelas yang belum terdaftar, dalam satu query.
        $unregisteredPerClass = User::query()
            ->siswa()
            ->active()
            ->whereNotNull('class_id')
            ->whereNotIn('id', $participantIds)
            ->select('class_id', DB::raw('count(*) as aggregate'))
            ->groupBy('class_id')
            ->pluck('aggregate', 'class_id');

        $classes = SchoolClass::query()
            ->active()
            ->ordered()
            ->withCount('students')
            ->get()
            ->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'name' => $class->name,
                'students_count' => (int) $class->students_count,
                'unregistered_count' => (int) ($unregisteredPerClass[$class->id] ?? 0),
            ]);

        return Inertia::render('Admin/Participants/Index', [
            'title' => 'Peserta: '.$exam->title,
            'exam' => $exam->only(['id', 'title', 'status']),
            'classes' => $classes,
            'participants' => $participants->map(fn (ExamParticipant $p) => [
                'id' => $p->id,
                'user_id' => $p->user_id,
                'name' => $p->user?->name ?? '(terhapus)',
                'username' => $p->user?->username,
                'class_name' => $p->user?->schoolClass?->name ?? '-',
                'is_active' => (bool) $p->is_active,
                'first_joined_at' => $p->first_joined_at?->toIso8601String(),
            ]),
            'availableUsers' => $availableUsers->map(fn ($u) => [
                'id' => $u->id,
                'label' => $u->displayLabel(),
            ]),
        ]);
    }

    public function store(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where('role', 'siswa'),
                Rule::unique('exam_participants', 'user_id')->where('exam_id', $exam->id),
            ],
        ], [
            'user_id.unique' => 'Siswa ini sudah terdaftar sebagai peserta.',
            'user_id.exists' => 'User tidak ditemukan atau bukan siswa.',
        ]);

        $participant = $exam->participants()->create([
            'user_id' => $validated['user_id'],
            'is_active' => true,
        ]);

        $this->audit->log(
            action: 'participant.added',
            subject: $participant,
            description: "Peserta ditambahkan ke ujian {$exam->title}.",
        );

        return back()->with('success', 'Peserta berhasil ditambahkan.');
    }

    /**
     * Tambah massal: seluruh siswa aktif dari SATU kelas sekaligus menjadi
     * peserta ujian (misal semua murid 7C). Siswa yang sudah terdaftar
     * dilewati.
     */
    public function bulk(Request $request, Exam $exam): RedirectResponse
    {
        $validated = $request->validate([
            'class_id' => ['required', 'integer', Rule::exists('classes', 'id')],
        ], [
            'class_id.required' => 'Pilih kelas terlebih dahulu.',
            'class_id.exists' => 'Kelas tidak ditemukan.',
        ]);

        $schoolClass = SchoolClass::findOrFail($validated['class_id']);

        $existing = $exam->participants()->pluck('user_id')->all();

        $students = User::query()
            ->siswa()
            ->active()
            ->where('class_id', $schoolClass->id)
            ->whereNotIn('id', $existing)
            ->orderBy('name')
            ->get();

        if ($students->isEmpty()) {
            return back()->with('warning', "Semua siswa kelas {$schoolClass->name} sudah terdaftar sebagai peserta.");
        }

        $added = 0;

        foreach ($students as $student) {
            $exam->participants()->create([
                'user_id' => $student->id,
                'is_active' => true,
            ]);

            $added++;
        }

        $this->audit->log(
            action: 'participant.bulk_class_added',
            subject: $exam,
            description: "{$added} siswa kelas {$schoolClass->name} ditambahkan massal ke ujian {$exam->title}.",
            meta: ['class_id' => $schoolClass->id, 'added' => $added],
        );

        return back()->with('success', "{$added} siswa kelas {$schoolClass->name} berhasil ditambahkan.");
    }

    public function destroy(Exam $exam, ExamParticipant $participant): RedirectResponse
    {
        if ((int) $participant->exam_id !== (int) $exam->id) {
            abort(404);
        }

        $hasAttempt = $participant->attempt()->exists();

        if ($hasAttempt) {
            $participant->update(['is_active' => false]);

            $this->audit->log(
                action: 'participant.deactivated',
                subject: $participant,
                description: 'Peserta dinonaktifkan (memiliki attempt, tidak dihapus).',
            );

            return back()->with('warning', 'Peserta sudah punya attempt sehingga hanya dinonaktifkan, tidak dihapus.');
        }

        $participant->delete();

        $this->audit->log(
            action: 'participant.removed',
            subject: $exam,
            description: 'Peserta dihapus dari ujian.',
        );

        return back()->with('success', 'Peserta berhasil dihapus.');
    }

}

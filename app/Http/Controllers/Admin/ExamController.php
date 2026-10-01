<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamRequest;
use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\QuestionBatch;
use App\Models\SchoolClass;
use App\Services\AuditLogService;
use App\Services\ExamTimerService;
use App\Services\QuestionCopyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamTimerService $timer,
        private readonly QuestionCopyService $copy,
        private readonly AuditLogService $audit,
    ) {}

    /**
     * Deploy peserta otomatis dari target ujian (jenjang/rombel) dan sinkron
     * bila target berganti. Peserta yang sudah punya attempt tidak pernah
     * dihapus — hanya peserta "murni" di luar target baru yang dikeluarkan.
     *
     * @return array{added: int, removed: int}
     */
    private function syncTargetParticipants(Exam $exam): array
    {
        $targetIds = $exam->targetStudentIds();
        $existing = $exam->participants()->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $toAdd = array_values(array_diff($targetIds->all(), $existing));

        $now = now();
        foreach (array_chunk($toAdd, 200) as $chunk) {
            $rows = array_map(fn (int $uid) => [
                'exam_id' => $exam->id,
                'user_id' => $uid,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk);

            ExamParticipant::insert($rows);
        }

        // Keluarkan peserta di luar target yang belum memulai attempt.
        // Proteksi lewat user_id (unique exam+user), bukan FK relasi —
        // attempt lama bisa punya exam_participant_id null.
        $attemptUserIds = $exam->attempts()->pluck('user_id')->map(fn ($id) => (int) $id)->all();

        $removed = $exam->participants()
            ->get()
            ->reject(fn (ExamParticipant $p) => in_array((int) $p->user_id, $attemptUserIds, true))
            ->reject(fn (ExamParticipant $p) => $exam->accessibleToUser($p->user))
            ->each->delete()
            ->count();

        if ($toAdd !== [] || $removed > 0) {
            $this->audit->log(
                action: 'exam.participants_synced',
                subject: $exam,
                description: 'Peserta disinkronkan dengan target ujian.',
                meta: ['target' => $exam->targetLabel(), 'added' => count($toAdd), 'removed' => $removed],
            );
        }

        return ['added' => count($toAdd), 'removed' => $removed];
    }

    public function index(Request $request): Response
    {
        $exams = Exam::with(['creator', 'participants', 'schoolClass'])
            ->withCount(['questions', 'attempts'])
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'target_label' => $exam->targetLabel(),
                'duration_minutes' => (int) $exam->duration_minutes,
                'start_at' => $exam->start_at?->toIso8601String(),
                'end_at' => $exam->end_at?->toIso8601String(),
                'status' => $exam->status->value,
                'status_label' => $exam->status->label(),
                'anti_cheat_enabled' => (bool) $exam->anti_cheat_enabled,
                'questions_count' => (int) $exam->questions_count,
                'participants_count' => $exam->participants->count(),
                'attempts_count' => (int) $exam->attempts_count,
                'active_attempts' => $exam->activeAttemptCount(),
                'creator_name' => $exam->creator?->name,
                'created_at' => $exam->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Exams/Index', [
            'title' => 'Manajemen Ujian',
            'exams' => $exams,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Exams/Form', [
            'title' => 'Buat Ujian',
            'edit' => false,
            'statusOptions' => ExamStatus::options(),
            'antiCheatActionOptions' => \App\Enums\AntiCheatAction::options(),
            'classes' => $this->classOptions(),
            'batches' => $this->batchOptions(),
        ]);
    }

    public function store(StoreExamRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $batchIds = array_map('intval', (array) ($data['batch_ids'] ?? []));
        unset($data['batch_ids']);

        $exam = Exam::create(array_merge($data, [
            'created_by' => $request->user()->id,
        ]));

        // Salin soal bank terpilih menjadi soal milik ujian ini.
        $copied = $this->copy->copyBatchesToExam($batchIds, $exam);

        // Peserta terisi otomatis dari target (jenjang/rombel) — tanpa ini,
        // admin harus menambah massal per rombel satu per satu.
        ['added' => $deployed] = $this->syncTargetParticipants($exam);

        return redirect()
            ->route('admin.exams.show', $exam)
            ->with('success', trim("Ujian dibuat dengan {$copied} soal dari bank.").' '
                .($deployed > 0 ? "{$deployed} peserta ter-deploy otomatis sesuai target." : ''));
    }

    public function show(Exam $exam): Response
    {
        $exam->loadCount(['questions', 'participants', 'attempts'])->load('schoolClass');

        return Inertia::render('Admin/Exams/Show', [
            'title' => $exam->title,
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'class_name' => $exam->schoolClass?->name,
                'target_label' => $exam->targetLabel(),
                'question_count' => $exam->question_count ? (int) $exam->question_count : null,
                'total_active_questions' => $exam->questions()->where('is_active', true)->count(),
                'duration_minutes' => (int) $exam->duration_minutes,
                'start_at' => $exam->start_at?->toIso8601String(),
                'end_at' => $exam->end_at?->toIso8601String(),
                'status' => $exam->status->value,
                'status_label' => $exam->status->label(),
                'anti_cheat_enabled' => (bool) $exam->anti_cheat_enabled,
                'anti_cheat_max_warnings' => (int) $exam->anti_cheat_max_warnings,
                'anti_cheat_action' => $exam->anti_cheat_action?->value,
                'require_fullscreen' => (bool) $exam->require_fullscreen,
                'offline_grace_minutes' => (int) $exam->offline_grace_minutes,
                'shuffle_questions' => (bool) $exam->shuffle_questions,
                'shuffle_options' => (bool) $exam->shuffle_options,
                'questions_count' => (int) $exam->questions_count,
                'participants_count' => $exam->participants->count(),
                'attempts_count' => (int) $exam->attempts_count,
                'active_attempts' => $exam->activeAttemptCount(),
                'activation_blockers' => $exam->activationBlockers(),
                'can_activate' => $exam->status === ExamStatus::Draft && $exam->canActivate(),
            ],
        ]);
    }

    public function edit(Exam $exam): Response
    {
        return Inertia::render('Admin/Exams/Form', [
            'title' => 'Edit: '.$exam->title,
            'edit' => true,
            'exam' => $exam->only([
                'id', 'title', 'description', 'class_id', 'grade', 'duration_minutes', 'question_count',
                'start_at', 'end_at', 'anti_cheat_enabled', 'anti_cheat_max_warnings',
                'anti_cheat_action', 'require_fullscreen', 'shuffle_questions', 'shuffle_options', 'offline_grace_minutes',
            ]),
            'statusOptions' => ExamStatus::options(),
            'antiCheatActionOptions' => \App\Enums\AntiCheatAction::options(),
            'classes' => $this->classOptions(),
            'batches' => [],
        ]);
    }

    public function update(StoreExamRequest $request, Exam $exam): RedirectResponse
    {
        $exam->update($request->validated());

        // Target boleh berganti (mis. jenjang 9 -> rombel 9A): peserta ikut
        // disinkronkan; siswa yang sudah terlanjur ber-attempt tidak disentuh.
        ['added' => $added, 'removed' => $removed] = $this->syncTargetParticipants($exam);

        $info = [];
        if ($added > 0) {
            $info[] = "{$added} peserta ditambahkan otomatis";
        }
        if ($removed > 0) {
            $info[] = "{$removed} peserta di luar target dikeluarkan";
        }

        return redirect()
            ->route('admin.exams.show', $exam)
            ->with('success', 'Ujian berhasil diperbarui.'
                .($info !== [] ? ' '.implode(', ', $info).'.' : ''));
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $exam->delete();

        return redirect()
            ->route('admin.exams.index')
            ->with('success', 'Ujian berhasil dihapus.');
    }

    // ------------------------------------------------------------------
    // Aksi transisi status
    // ------------------------------------------------------------------

    public function activate(Exam $exam, Request $request): RedirectResponse
    {
        try {
            $this->timer->activateExam($exam, $request->user());
        } catch (\App\Services\ExamNotReadyException $e) {
            return back()->with('error', 'Ujian belum bisa diaktifkan: '.implode(' ', $e->blockers));
        }

        return back()->with('success', 'Ujian berhasil diaktifkan.');
    }

    public function pause(Exam $exam, Request $request): RedirectResponse
    {
        $this->timer->pauseExam($exam, $request->user());

        return back()->with('success', 'Ujian berhasil dijeda.');
    }

    public function resume(Exam $exam, Request $request): RedirectResponse
    {
        $this->timer->resumeExam($exam, $request->user());

        return back()->with('success', 'Ujian berhasil dilanjutkan.');
    }

    public function close(Exam $exam, Request $request): RedirectResponse
    {
        $this->timer->closeExam($exam, $request->user());

        return back()->with('success', 'Ujian berhasil ditutup.');
    }

    public function closeAndAutoSubmit(Exam $exam, Request $request): RedirectResponse
    {
        $count = $this->timer->closeAndAutoSubmitExam($exam, $request->user());

        return back()->with('success', "Ujian ditutup dan {$count} attempt disubmit paksa.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function classOptions(): array
    {
        return SchoolClass::query()->active()->ordered()->get()
            ->map(fn (SchoolClass $c) => ['value' => (int) $c->id, 'label' => $c->name])
            ->all();
    }

    /**
     * Daftar bank soal yang bisa dipilih untuk ujian (beserta jumlah soalnya).
     *
     * @return array<int, array<string, mixed>>
     */
    private function batchOptions(): array
    {
        return QuestionBatch::query()
            ->with('schoolClass')
            ->withCount(['questions' => fn ($q) => $q->whereNull('exam_id')->where('is_active', true)])
            ->ordered()
            ->get()
            ->map(fn (QuestionBatch $b) => [
                'value' => (int) $b->id,
                'label' => $b->name,
                'class_name' => $b->schoolClass?->name,
                'questions_count' => (int) $b->questions_count,
            ])
            ->all();
    }
}
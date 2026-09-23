<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreExamRequest;
use App\Models\Exam;
use App\Services\ExamTimerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ExamController extends Controller
{
    public function __construct(private readonly ExamTimerService $timer) {}

    public function index(Request $request): Response
    {
        $exams = Exam::with(['creator', 'participants'])
            ->withCount(['questions', 'attempts'])
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
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
        ]);
    }

    public function store(StoreExamRequest $request): RedirectResponse
    {
        $exam = Exam::create(array_merge($request->validated(), [
            'created_by' => $request->user()->id,
        ]));

        return redirect()
            ->route('admin.exams.show', $exam)
            ->with('success', 'Ujian berhasil dibuat.');
    }

    public function show(Exam $exam): Response
    {
        $exam->loadCount(['questions', 'participants', 'attempts']);

        return Inertia::render('Admin/Exams/Show', [
            'title' => $exam->title,
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'duration_minutes' => (int) $exam->duration_minutes,
                'start_at' => $exam->start_at?->toIso8601String(),
                'end_at' => $exam->end_at?->toIso8601String(),
                'status' => $exam->status->value,
                'status_label' => $exam->status->label(),
                'anti_cheat_enabled' => (bool) $exam->anti_cheat_enabled,
                'anti_cheat_max_warnings' => (int) $exam->anti_cheat_max_warnings,
                'anti_cheat_action' => $exam->anti_cheat_action?->value,
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
                'id', 'title', 'description', 'duration_minutes',
                'start_at', 'end_at', 'anti_cheat_enabled', 'anti_cheat_max_warnings',
                'anti_cheat_action', 'shuffle_questions', 'shuffle_options', 'offline_grace_minutes',
            ]),
            'statusOptions' => ExamStatus::options(),
            'antiCheatActionOptions' => \App\Enums\AntiCheatAction::options(),
        ]);
    }

    public function update(StoreExamRequest $request, Exam $exam): RedirectResponse
    {
        $exam->update($request->validated());

        return redirect()
            ->route('admin.exams.show', $exam)
            ->with('success', 'Ujian berhasil diperbarui.');
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
}
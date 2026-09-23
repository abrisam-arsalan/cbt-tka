<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AntiCheatLog;
use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AntiCheatLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AntiCheatLog::query()
            ->with(['attempt.user.schoolClass', 'attempt.exam'])
            ->when($request->filled('exam_id'), fn ($q) => $q->whereHas('attempt', fn ($q) => $q->where('exam_id', (int) $request->input('exam_id'))))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->input('type')))
            ->recent()
            ->paginate(30)
            ->withQueryString();

        $exams = Exam::query()
            ->whereHas('attempts.antiCheatLogs')
            ->orderByDesc('id')
            ->get(['id', 'title']);

        return Inertia::render('Admin/AntiCheat/Index', [
            'title' => 'Log Anti-Cheat',
            'logs' => $logs->through(fn (AntiCheatLog $log) => [
                'id' => $log->id,
                'attempt_id' => $log->attempt_id,
                'type' => $log->type->value,
                'type_label' => $log->type->label(),
                'message' => $log->message,
                'meta' => $log->meta,
                'student_name' => $log->attempt?->user?->name ?? '(terhapus)',
                'class_name' => $log->attempt?->user?->schoolClass?->name ?? '-',
                'exam_title' => $log->attempt?->exam?->title ?? '-',
                'warnings_count' => (int) ($log->attempt?->warnings_count ?? 0),
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
            'exams' => $exams,
            'typeOptions' => \App\Enums\AntiCheatEventType::options(),
            'filters' => [
                'exam_id' => (string) $request->input('exam_id', ''),
                'type' => (string) $request->input('type', ''),
            ],
        ]);
    }

    /**
     * Detail seluruh kejadian pada satu attempt.
     */
    public function show(Attempt $attempt): Response
    {
        $attempt->load(['user.schoolClass', 'exam', 'antiCheatLogs']);

        return Inertia::render('Admin/AntiCheat/Show', [
            'title' => 'Detail Anti-Cheat: '.$attempt->user?->name,
            'attempt' => [
                'id' => $attempt->id,
                'student_name' => $attempt->user?->name ?? '(terhapus)',
                'username' => $attempt->user?->username,
                'class_name' => $attempt->user?->schoolClass?->name ?? '-',
                'exam_title' => $attempt->exam?->title ?? '-',
                'status' => $attempt->status->value,
                'status_label' => $attempt->status->label(),
                'warnings_count' => (int) $attempt->warnings_count,
                'anti_cheat_enforced' => (bool) $attempt->anti_cheat_enforced,
                'anti_cheat_action' => $attempt->exam?->anti_cheat_action?->label(),
                'max_warnings' => $attempt->exam?->maxWarnings() ?? 0,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
            ],
            'logs' => $attempt->antiCheatLogs->map(fn (AntiCheatLog $log) => [
                'id' => $log->id,
                'type' => $log->type->value,
                'type_label' => $log->type->label(),
                'message' => $log->message,
                'meta' => $log->meta,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
        ]);
    }
}

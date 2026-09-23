<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ExamStatus;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $userCount = \App\Models\User::count();
        $classCount = \App\Models\SchoolClass::count();
        $examCount = Exam::count();
        $activeExamCount = Exam::where('status', ExamStatus::Active->value)->count();
        $attemptCount = \App\Models\Attempt::count();
        $recentLogs = \App\Models\AuditLog::with('user')->recent()->limit(10)->get();

        $activeExams = Exam::withCount(['attempts'])
            ->where('status', ExamStatus::Active->value)
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => $exam->id,
                'title' => $exam->title,
                'start_at' => $exam->start_at?->toIso8601String(),
                'end_at' => $exam->end_at?->toIso8601String(),
                'duration_minutes' => (int) $exam->duration_minutes,
                'attempts_count' => (int) $exam->attempts_count,
                'active_attempts_count' => $exam->activeAttemptCount(),
            ]);

        return Inertia::render('Admin/Dashboard', [
            'title' => 'Dashboard Admin',
            'stats' => [
                'users' => $userCount,
                'classes' => $classCount,
                'exams' => $examCount,
                'active_exams' => $activeExamCount,
                'attempts' => $attemptCount,
            ],
            'activeExams' => $activeExams,
            'recentLogs' => $recentLogs->map(fn ($log) => [
                'action' => $log->action,
                'description' => $log->description,
                'actor' => $log->user?->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ]),
        ]);
    }
}
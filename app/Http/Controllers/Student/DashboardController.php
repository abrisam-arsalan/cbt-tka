<?php

namespace App\Http\Controllers\Student;

use App\Enums\AttemptStatus;
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
        $user = $request->user();

        $examIds = $user->examParticipations()
            ->where('is_active', true)
            ->pluck('exam_id');

        $userGrade = $user->schoolClass?->grade;

        $exams = Exam::query()
            ->whereIn('id', $examIds)
            ->where(function ($q) use ($user, $userGrade) {
                // Semua siswa: class_id & grade kosong.
                $q->where(fn ($a) => $a->whereNull('class_id')->whereNull('grade'))
                    // Rombel tertentu.
                    ->orWhere('class_id', $user->class_id);

                // Jenjang: siswa hanya melihat ujian jenjang kelasnya sendiri.
                if ($userGrade !== null && $userGrade !== '') {
                    $q->orWhere(fn ($g) => $g->whereNull('class_id')->where('grade', (string) $userGrade));
                }
            })
            ->withCount(['questions' => fn ($q) => $q->where('is_active', true)])
            ->orderByDesc('start_at')
            ->orderByDesc('id')
            ->get()
            ->map(function (Exam $exam) use ($user) {
                $attempt = $user->attempts()->where('exam_id', $exam->id)->first();

                return [
                    'id' => $exam->id,
                    'title' => $exam->title,
                    'description' => $exam->description,
                    'start_at' => $exam->start_at?->toIso8601String(),
                    'end_at' => $exam->end_at?->toIso8601String(),
                    'duration_minutes' => (int) $exam->duration_minutes,
                    'status' => $exam->status->value,
                    'status_label' => $exam->status->label(),
                    'is_joinable' => $exam->isJoinableNow(),
                    'unavailable_reason' => $exam->unavailableReason(),
                    'questions_count' => $exam->effectiveQuestionCount(),
                    'attempt_status' => $attempt?->status->value,
                    'attempt_status_label' => $attempt?->status->label(),
                    'attempt_score' => $attempt?->score !== null ? (float) $attempt->score : null,
                ];
            });

        return Inertia::render('Student/Dashboard', [
            'title' => 'Dashboard Siswa',
            'exams' => $exams,
            'activeCount' => $exams->where('attempt_status', AttemptStatus::InProgress->value)->count(),
            'completedCount' => $exams->where('attempt_status', AttemptStatus::Submitted->value)->count(),
        ]);
    }
}

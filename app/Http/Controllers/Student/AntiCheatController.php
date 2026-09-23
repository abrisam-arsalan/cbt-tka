<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\AntiCheatReportRequest;
use App\Models\Attempt;
use App\Models\Exam;
use App\Services\AntiCheatService;
use App\Services\ExamTimerService;
use Illuminate\Http\JsonResponse;

class AntiCheatController extends Controller
{
    public function store(Exam $exam, AntiCheatReportRequest $request, AntiCheatService $ac, ExamTimerService $timer): JsonResponse
    {
        $user = $request->user();

        $attempt = Attempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $summary = $ac->recordBatch($attempt, $request->validated('events'));

        // Bila kebijakan anti-cheat memerintahkan auto-submit, lakukan segera.
        if ($summary['auto_submit'] ?? false) {
            $timer->submit($attempt->fresh(), \App\Enums\SubmitReason::AntiCheatLimit, (string) $request->ip());
            $summary['auto_submitted'] = true;
        }

        return response()->json($summary);
    }
}
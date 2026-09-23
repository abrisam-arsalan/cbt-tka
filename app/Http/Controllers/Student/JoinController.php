<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\JoinExamRequest;
use App\Models\Exam;
use App\Models\Attempt;
use App\Services\CardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JoinController extends Controller
{
    public function show(Exam $exam): Response
    {
        return Inertia::render('Student/Join', [
            'title' => 'Ikut Ujian',
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'duration_minutes' => (int) $exam->duration_minutes,
                'start_at' => $exam->start_at?->toIso8601String(),
                'end_at' => $exam->end_at?->toIso8601String(),
                'questions_count' => $exam->questions()->where('is_active', true)->count(),
            ],
        ]);
    }

    public function store(JoinExamRequest $request, Exam $exam, CardService $cards): RedirectResponse
    {
        $user = $request->user();

        $participant = $cards->verify($exam, (string) $request->input('token'));

        if ($participant === null) {
            return back()->withErrors([
                'token' => 'Token tidak valid atau peserta tidak ditemukan.',
            ]);
        }

        if ((int) $participant->user_id !== (int) $user->id) {
            return back()->withErrors([
                'token' => 'Token ini bukan milik akun Anda.',
            ]);
        }

        if (! $exam->isJoinableNow()) {
            return back()->withErrors([
                'token' => $exam->unavailableReason() ?? 'Ujian tidak bisa diikuti saat ini.',
            ]);
        }

        $attempt = Attempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $user->id)
            ->first();

        if ($attempt !== null) {
            return redirect()->route('student.exam.run', $exam);
        }

        return redirect()->route('student.exam.run', $exam);
    }
}
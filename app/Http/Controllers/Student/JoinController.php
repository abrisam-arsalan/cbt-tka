<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\JoinExamRequest;
use App\Models\Exam;
use App\Models\Attempt;
use App\Services\ExamSessionTokenService;
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

    public function store(JoinExamRequest $request, Exam $exam, ExamSessionTokenService $tokens): RedirectResponse
    {
        $user = $request->user();

        // Siswa harus terdaftar sebagai peserta (dikelola admin via menu Peserta).
        $isParticipant = $exam->participants()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();

        if (! $isParticipant) {
            return back()->withErrors([
                'token' => 'Anda tidak terdaftar sebagai peserta ujian ini. Hubungi panitia.',
            ]);
        }

        // Token ujian (satu tetap per ujian, berlaku selama ujian berlangsung)
        // diumumkan pengawas dari halaman Monitoring.
        if (! $tokens->verify($exam, (string) $request->input('token'))) {
            return back()->withErrors([
                'token' => 'Token tidak dikenali. Periksa kembali ejaannya (tanda hubung/spasi/huruf kecil tidak masalah) atau tanya pengawas.',
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
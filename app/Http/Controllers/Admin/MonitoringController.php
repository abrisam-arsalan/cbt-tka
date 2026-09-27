<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Exam;
use App\Services\ExamSessionTokenService;
use App\Services\ExamTimerService;
use App\Services\PresenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MonitoringController extends Controller
{
    public function __construct(
        private readonly PresenceService $presence,
        private readonly ExamTimerService $timer,
        private readonly ExamSessionTokenService $tokens,
    ) {}

    public function index(Exam $exam): Response
    {
        $rows = $this->presence->monitoringRows($exam);
        $summary = $this->presence->summarize($rows);

        // Token sesi aktif — diberitahukan pengawas ke siswa di ruang ujian.
        $session = $this->tokens->current($exam);

        return Inertia::render('Admin/Monitoring/Index', [
            'title' => 'Monitoring: '.$exam->title,
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'status' => $exam->status->value,
                'duration_minutes' => (int) $exam->duration_minutes,
                'grace_minutes' => $exam->graceMinutes(),
            ],
            'session_token' => [
                'token' => $session['token'],
                'expires_at' => $session['expires_at'],
            ],
            'summary' => $summary,
            'rows' => $rows,
            'presence_driver' => $this->presence->driverName(),
            'refresh_seconds' => (int) config('cbt.presence.online_seconds', 45),
        ]);
    }

    public function extend(Exam $exam, Attempt $attempt, Request $request): RedirectResponse
    {
        $minutes = (int) $request->input('minutes', 5);

        if ($minutes < 1 || $minutes > 120) {
            return back()->with('error', 'Perpanjangan harus antara 1 dan 120 menit.');
        }

        $this->timer->extendAttempt($attempt, $minutes, $request->user());

        return back()->with('success', "Waktu diperpanjang {$minutes} menit.");
    }

    public function unlock(Exam $exam, Attempt $attempt, Request $request): RedirectResponse
    {
        $this->timer->unlockAttempt($attempt, $request->user());

        return back()->with('success', 'Attempt berhasil dibuka.');
    }

    public function resetWarnings(Exam $exam, Attempt $attempt, Request $request): RedirectResponse
    {
        $this->timer->resetWarnings($attempt, $request->user());

        return back()->with('success', 'Peringatan anti-cheat direset.');
    }

    public function forceSubmit(Exam $exam, Attempt $attempt, Request $request): RedirectResponse
    {
        $this->timer->forceSubmitAttempt($attempt, $request->user());

        return back()->with('success', 'Attempt disubmit paksa.');
    }
}
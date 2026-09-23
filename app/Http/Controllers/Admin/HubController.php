<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AttemptStatus;
use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Exam;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman "hub" (titik masuk) untuk menu top-level di sidebar admin.
 *
 * Sebagian fitur (bank soal, kartu, peserta, impor) secara domain terikat ke
 * satu ujian (route nested admin.exams.*). Halaman-halaman ini menyajikan
 * daftar ujian sebagai pintu masuk, sedangkan detailnya tetap di route nested.
 * Monitoring dan Hasil disajikan sebagai pandangan global lintas ujian.
 */
class HubController extends Controller
{
    public function bankSoal(): Response
    {
        return $this->examHub('Bank Soal', 'questions');
    }

    public function importSoal(): Response
    {
        $exams = Exam::query()
            ->withCount(['questions'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => (int) $exam->id,
                'title' => $exam->title,
                'status' => $exam->status->value,
                'status_label' => $exam->status->label(),
                'questions_count' => (int) $exam->questions_count,
            ]);

        return Inertia::render('Admin/Import/Index', [
            'title' => 'Impor Soal',
            'exams' => $exams,
            'typeOptions' => \App\Enums\QuestionType::options(),
        ]);
    }

    public function cards(): Response
    {
        return $this->examHub('Cetak Kartu', 'cards');
    }

    public function peserta(): Response
    {
        return $this->examHub('Peserta', 'participants');
    }

    /**
     * Monitoring global: seluruh attempt yang sedang berjalan lintas ujian.
     */
    public function monitoring(): Response
    {
        $now = now();

        $rows = Attempt::query()
            ->with(['user.schoolClass', 'exam'])
            ->withCount('answers')
            ->whereIn('status', [
                AttemptStatus::InProgress->value,
                AttemptStatus::Locked->value,
                AttemptStatus::Expired->value,
            ])
            ->orderByDesc('id')
            ->get()
            ->map(function (Attempt $attempt) use ($now) {
                $total = (int) $attempt->total_questions;
                $answered = (int) $attempt->answers_count;

                return [
                    'attempt_id' => $attempt->id,
                    'name' => $attempt->user?->name ?? '(siswa terhapus)',
                    'username' => $attempt->user?->username,
                    'class_name' => $attempt->user?->schoolClass?->name ?? '-',
                    'exam_title' => $attempt->exam?->title ?? '(ujian terhapus)',
                    'exam_id' => (int) $attempt->exam_id,
                    'status' => $attempt->status->value,
                    'status_label' => $attempt->status->label(),
                    'answered_count' => $answered,
                    'total_questions' => $total,
                    'progress_percent' => $total > 0
                        ? round(min(100, ($answered / $total) * 100), 1)
                        : 0.0,
                    'remaining_seconds' => $attempt->remainingSeconds($now),
                    'warnings_count' => (int) $attempt->warnings_count,
                    'deadline_at' => $attempt->deadline_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Admin/Monitoring/Global', [
            'title' => 'Monitoring Ujian',
            'rows' => $rows,
        ]);
    }

    /**
     * Hasil ujian: seluruh attempt yang sudah dinilai.
     */
    public function hasil(): Response
    {
        $rows = Attempt::query()
            ->with(['user.schoolClass', 'exam'])
            ->where('status', AttemptStatus::Submitted->value)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->map(fn (Attempt $attempt) => [
                'id' => (int) $attempt->id,
                'name' => $attempt->user?->name ?? '(siswa terhapus)',
                'username' => $attempt->user?->username,
                'class_name' => $attempt->user?->schoolClass?->name ?? '-',
                'exam_title' => $attempt->exam?->title ?? '(ujian terhapus)',
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'correct_count' => (int) $attempt->correct_count,
                'wrong_count' => (int) $attempt->wrong_count,
                'unanswered_count' => (int) $attempt->unanswered_count,
                'total_questions' => (int) $attempt->total_questions,
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'submit_reason_label' => $attempt->submit_reason?->label(),
            ]);

        return Inertia::render('Admin/Results/Index', [
            'title' => 'Hasil Ujian',
            'rows' => $rows,
        ]);
    }

    /**
     * Daftar ujian sebagai pintu masuk fitur yang terikat pada satu ujian.
     *
     * @param  string  $mode  questions | cards | participants | import
     */
    private function examHub(string $title, string $mode): Response
    {
        $exams = Exam::query()
            ->withCount(['questions', 'participants', 'attempts'])
            ->orderByDesc('id')
            ->get()
            ->map(fn (Exam $exam) => [
                'id' => (int) $exam->id,
                'title' => $exam->title,
                'status' => $exam->status->value,
                'status_label' => $exam->status->label(),
                'questions_count' => (int) $exam->questions_count,
                'participants_count' => (int) $exam->participants_count,
                'attempts_count' => (int) $exam->attempts_count,
            ]);

        return Inertia::render('Admin/Shared/ExamHub', [
            'title' => $title,
            'mode' => $mode,
            'exams' => $exams,
        ]);
    }
}

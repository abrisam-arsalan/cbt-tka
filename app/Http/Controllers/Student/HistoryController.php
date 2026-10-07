<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Services\ScoringService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HistoryController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $attempts = Attempt::query()
            ->with('exam')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(function (Attempt $attempt) {
                return [
                    'id' => $attempt->id,
                    'exam_id' => $attempt->exam_id,
                    'exam_title' => $attempt->exam?->title ?? '(ujian terhapus)',
                    'status' => $attempt->status->value,
                    'status_label' => $attempt->status->label(),
                    'started_at' => $attempt->started_at?->toIso8601String(),
                    'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                    'total_questions' => (int) $attempt->total_questions,
                    'correct_count' => (int) $attempt->correct_count,
                    'wrong_count' => (int) $attempt->wrong_count,
                    'unanswered_count' => (int) $attempt->unanswered_count,
                    'score' => $attempt->score !== null ? (float) $attempt->score : null,
                    'submit_reason' => $attempt->submit_reason?->value,
                    'submit_reason_label' => $attempt->submit_reason?->label(),
                ];
            });

        return Inertia::render('Student/History', [
            'title' => 'Riwayat Ujian',
            'attempts' => $attempts,
        ]);
    }

    public function show(Attempt $attempt, Request $request): Response
    {
        if ((int) $attempt->user_id !== (int) $request->user()->id) {
            abort(403);
        }

        $attempt->load('exam.questions.options', 'exam.questions.matchingPairs', 'answers');

        $questions = $attempt->exam?->questions()
            ->with(['options', 'matchingPairs'])
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        // Pembahasan hanya untuk subset soal yang memang keluar di attempt ini.
        if ($attempt->exam?->question_count) {
            $keep = $attempt->exam->questionSubsetIds((int) $attempt->shuffle_seed);
            $questions = $questions->filter(fn ($q) => in_array((int) $q->id, $keep, true))->values();
        }

        $answers = $attempt->answers->keyBy('question_id');

        // Sumber kebenaran pembahasan = ScoringService yang SAMA dengan
        // penilaian saat submit (persist: false — tidak menulis apa pun).
        // Dulu badge Benar/Salah membaca field is_correct yang ternyata tidak
        // pernah dikirim payload => semua soal berjawaban tampil 'Salah'
        // walau jawabannya hijau benar.
        $verdicts = collect(app(ScoringService::class)->scoreAttempt($attempt, persist: false)->perQuestion)
            ->keyBy('question_id');

        return Inertia::render('Student/Result', [
            'title' => 'Hasil: '.$attempt->exam?->title,
            'attempt' => [
                'id' => $attempt->id,
                'exam_title' => $attempt->exam?->title,
                'status' => $attempt->status->value,
                'started_at' => $attempt->started_at?->toIso8601String(),
                'submitted_at' => $attempt->submitted_at?->toIso8601String(),
                'total_questions' => (int) $attempt->total_questions,
                'correct_count' => (int) $attempt->correct_count,
                'wrong_count' => (int) $attempt->wrong_count,
                'unanswered_count' => (int) $attempt->unanswered_count,
                'score' => $attempt->score !== null ? (float) $attempt->score : null,
                'submit_reason_label' => $attempt->submit_reason?->label(),
            ],
            'details' => $questions->map(function ($question) use ($answers, $verdicts) {
                $answer = $answers[$question->id] ?? null;
                $verdict = $verdicts->get((int) $question->id);

                return [
                    'question_id' => (int) $question->id,
                    'number' => (int) $question->order,
                    'type' => $question->type->value,
                    'type_label' => $question->type->label(),
                    'question_text' => $question->question_text,
                    'media_url' => $question->media_url,
                    // answered/is_correct/strings SEMUA dari verdict penilaian —
                    // badge tak akan pernah menyimpang dari nilai yang dihitung.
                    'answered' => (bool) ($verdict['answered'] ?? ($answer !== null && ! $answer->isBlank())),
                    'is_correct' => (bool) ($verdict['is_correct'] ?? false),
                    'student_answer' => $verdict['student_answer'] ?? null,
                    'correct_answer' => $verdict['correct_answer'] ?? null,
                    'options' => $question->options->map(fn ($o) => [
                        'id' => (int) $o->id,
                        'label' => $o->label,
                        'text' => $o->option_text,
                        'is_correct' => (bool) $o->is_correct,
                        'selected' => false, // diisi oleh Vue dari answers payload
                    ])->all(),
                    'pairs' => $question->matchingPairs->map(fn ($p) => [
                        'id' => (int) $p->id,
                        'left' => $p->left_text,
                        'right' => $p->right_text,
                    ])->all(),
                    'answer_payload' => $answer?->answer_payload,
                    'late_sync_flag' => (bool) ($answer?->late_sync_flag ?? false),
                ];
            })->all(),
        ]);
    }
}
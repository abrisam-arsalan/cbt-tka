<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
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

        $answers = $attempt->answers->keyBy('question_id');

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
            'details' => $questions->map(function ($question) use ($answers) {
                $answer = $answers[$question->id] ?? null;

                return [
                    'number' => (int) $question->order,
                    'type' => $question->type->value,
                    'type_label' => $question->type->label(),
                    'question_text' => $question->question_text,
                    'media_url' => $question->media_url,
                    'answered' => $answer !== null && ! $answer->isBlank(),
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
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Models\Exam;
use App\Models\Question;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestionController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request, Exam $exam): Response
    {
        $questions = $exam->questions()
            ->with(['options', 'matchingPairs'])
            ->ordered()
            ->get();

        return Inertia::render('Admin/Questions/Index', [
            'title' => 'Bank Soal: '.$exam->title,
            'exam' => $exam->only(['id', 'title', 'status']),
            'questions' => $questions->map(fn (Question $question) => [
                'id' => $question->id,
                'order' => (int) $question->order,
                'type' => $question->type->value,
                'type_label' => $question->type->label(),
                'stimulus' => $question->stimulus,
                'question_text' => $question->question_text,
                'is_active' => (bool) $question->is_active,
                'item_count' => $question->itemCount(),
                'has_valid_key' => $question->hasValidKey(),
            ]),
        ]);
    }

    public function create(Exam $exam): Response
    {
        return Inertia::render('Admin/Questions/Form', [
            'title' => 'Tambah Soal',
            'exam' => $exam->only(['id', 'title']),
            'edit' => false,
            'question' => null,
            'typeOptions' => \App\Enums\QuestionType::options(),
        ]);
    }

    public function store(StoreQuestionRequest $request, Exam $exam): RedirectResponse
    {
        $question = $this->saveQuestion($request, $exam);

        $this->audit->log(
            action: 'question.created',
            subject: $question,
            description: "Soal #{$question->order} ditambahkan ke ujian {$exam->title}.",
        );

        return redirect()
            ->route('admin.exams.questions.index', $exam)
            ->with('success', 'Soal berhasil ditambahkan.');
    }

    public function edit(Exam $exam, Question $question): Response
    {
        $question->load(['options', 'matchingPairs']);

        return Inertia::render('Admin/Questions/Form', [
            'title' => 'Edit Soal #'.$question->order,
            'exam' => $exam->only(['id', 'title']),
            'edit' => true,
            'question' => [
                'id' => $question->id,
                'type' => $question->type->value,
                'stimulus' => $question->stimulus,
                'question_text' => $question->question_text,
                'media_url' => $question->media_url,
                'order' => (int) $question->order,
                'is_active' => (bool) $question->is_active,
                'options' => $question->options->map(fn ($o) => [
                    'label' => $o->label,
                    'option_text' => $o->option_text,
                    'is_correct' => (bool) $o->is_correct,
                ])->all(),
                'matching_pairs' => $question->matchingPairs->map(fn ($p) => [
                    'left_text' => $p->left_text,
                    'right_text' => $p->right_text,
                ])->all(),
            ],
            'typeOptions' => \App\Enums\QuestionType::options(),
        ]);
    }

    public function update(StoreQuestionRequest $request, Exam $exam, Question $question): RedirectResponse
    {
        $question = $this->saveQuestion($request, $exam, $question);

        $this->audit->log(
            action: 'question.updated',
            subject: $question,
            description: "Soal #{$question->order} pada ujian {$exam->title} diubah.",
        );

        return redirect()
            ->route('admin.exams.questions.index', $exam)
            ->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroy(Exam $exam, Question $question): RedirectResponse
    {
        $answersCount = $question->answers()->count();

        if ($answersCount > 0 && $exam->attempts()->where('status', '!=', 'submitted')->exists()) {
            return back()->with('error', 'Soal ini sudah punya jawaban siswa pada attempt aktif. Nonaktifkan soal (is_active=false) daripada menghapusnya.');
        }

        $order = $question->order;
        $question->delete();

        $this->audit->log(
            action: 'question.deleted',
            subject: $exam,
            description: "Soal #{$order} dihapus dari ujian {$exam->title}.",
        );

        return back()->with('success', 'Soal berhasil dihapus.');
    }

    /**
     * Simpan soal + kunci. Opsi/pasangan lama diganti seluruhnya supaya
     * konsisten dengan form yang mengirim daftar lengkap.
     */
    private function saveQuestion(StoreQuestionRequest $request, Exam $exam, ?Question $existing = null): Question
    {
        $validated = $request->validated();
        $type = \App\Enums\QuestionType::from($validated['type']);

        $data = [
            'type' => $type->value,
            'stimulus' => $validated['stimulus'] ?? null,
            'question_text' => $validated['question_text'],
            'media_url' => $validated['media_url'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($existing === null) {
            $data['order'] = (int) ($exam->questions()->max('order') ?? 0) + 1;
            $question = $exam->questions()->create($data);
        } else {
            $data['order'] = (int) $validated['order'];
            $existing->update($data);
            $question = $existing;
        }

        $question->options()->delete();
        $question->matchingPairs()->delete();

        if ($type->usesOptions()) {
            $order = 0;
            foreach ($validated['options'] ?? [] as $option) {
                if (trim((string) $option['option_text']) === '') {
                    continue;
                }

                $question->options()->create([
                    'label' => $option['label'] ?? chr(ord('A') + $order),
                    'option_text' => $option['option_text'],
                    'media_url' => $option['media_url'] ?? null,
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'order' => $order++,
                ]);
            }
        }

        if ($type === \App\Enums\QuestionType::Matching) {
            $order = 0;
            foreach ($validated['matching_pairs'] ?? [] as $pair) {
                if (trim((string) ($pair['left_text'] ?? '')) === '' || trim((string) ($pair['right_text'] ?? '')) === '') {
                    continue;
                }

                $question->matchingPairs()->create([
                    'left_text' => $pair['left_text'],
                    'right_text' => $pair['right_text'],
                    'left_media_url' => $pair['left_media_url'] ?? null,
                    'right_media_url' => $pair['right_media_url'] ?? null,
                    'order' => $order++,
                ]);
            }
        }

        return $question->refresh();
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreQuestionRequest;
use App\Models\MatchingPair;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionBatch;
use App\Models\SchoolClass;
use App\Services\AuditLogService;
use App\Services\QuestionBankImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Bank Soal: kumpulan soal hasil impor yang bisa dipilih ulang untuk ujian.
 * Satu batch = satu "Bank" (nama + tanggal). Mengedit batch tidak memengaruhi
 * ujian yang sudah menyalin isinya (lihat QuestionCopyService).
 */
class BankController extends Controller
{
    public function __construct(
        private readonly QuestionBankImportService $imports,
        private readonly AuditLogService $audit,
    ) {}

    public function index(Request $request): Response
    {
        $batches = QuestionBatch::query()
            ->with('schoolClass')
            ->withCount(['questions' => fn ($q) => $q->whereNull('exam_id')])
            ->ordered()
            ->get()
            ->map(fn (QuestionBatch $b) => [
                'id' => (int) $b->id,
                'name' => $b->name,
                'class_name' => $b->schoolClass?->name,
                'questions_count' => (int) $b->questions_count,
                'created_at' => $b->created_at?->toIso8601String(),
                'created_at_label' => $b->created_at?->translatedFormat('d M Y H:i'),
            ]);

        return Inertia::render('Admin/Bank/Index', [
            'title' => 'Bank Soal',
            'batches' => $batches,
            'importErrors' => $request->session()->pull('import_errors', []),
        ]);
    }

    public function template(): BinaryFileResponse
    {
        $path = $this->imports->downloadTemplate();

        return response()->download($path, 'template-bank-soal.csv')->deleteFileAfterSend();
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Admin/Bank/Import', [
            'title' => 'Impor Bank Soal',
            'classes' => $this->classOptions(),
            'columns' => QuestionBankImportService::COLUMNS,
            'importErrors' => $request->session()->pull('import_errors', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ], [
            'file.mimes' => 'File harus berformat CSV atau XLSX.',
            'file.max' => 'Ukuran file maksimum 5 MB.',
        ]);

        $result = $this->imports->import(
            $request->file('file'),
            $validated['name'],
            $validated['class_id'] ?? null,
            $request->user(),
        );

        if ($result['batch'] === null) {
            $request->session()->put('import_errors', $result['errors']);

            return redirect()
                ->route('admin.bank.create')
                ->with('error', 'Tidak ada soal yang valid. Periksa detail error di bawah.');
        }

        if ($result['errors'] !== []) {
            $request->session()->put('import_errors', $result['errors']);
        }

        $message = "Bank \"{$result['batch']->name}\" dibuat dengan {$result['imported']} soal"
            .($result['errors'] !== [] ? ', '.count($result['errors']).' baris dilewati.' : '.');

        return redirect()
            ->route('admin.bank.show', $result['batch'])
            ->with('success', $message);
    }

    /**
     * Halaman "Kelola" — daftar soal dalam satu bank + aksi tambah/ubah/hapus.
     */
    public function show(QuestionBatch $batch): Response
    {
        $batch->load('schoolClass');

        $questions = $batch->questions()
            ->with(['options', 'matchingPairs'])
            ->whereNull('exam_id')
            ->ordered()
            ->get();

        return Inertia::render('Admin/Bank/Manage', [
            'title' => 'Kelola: '.$batch->name,
            'batch' => [
                'id' => (int) $batch->id,
                'name' => $batch->name,
                'class_name' => $batch->schoolClass?->name,
                'created_at_label' => $batch->created_at?->translatedFormat('d M Y H:i'),
            ],
            'questions' => $questions->map(fn (Question $q) => [
                'id' => $q->id,
                'order' => (int) $q->order,
                'type' => $q->type->value,
                'type_label' => $q->type->label(),
                'question_text' => $q->question_text,
                'item_count' => $q->itemCount(),
                'has_valid_key' => $q->hasValidKey(),
                'is_active' => (bool) $q->is_active,
            ]),
        ]);
    }

    public function destroy(QuestionBatch $batch): RedirectResponse
    {
        $name = $batch->name;
        $batch->delete();

        $this->audit->log(action: 'question_batch.deleted', description: "Bank \"{$name}\" dihapus.");

        return redirect()->route('admin.bank.index')->with('success', "Bank \"{$name}\" dihapus.");
    }

    // ------------------------------------------------------------------
    // Kelola satu soal di dalam bank
    // ------------------------------------------------------------------

    public function createQuestion(QuestionBatch $batch): Response
    {
        return Inertia::render('Admin/Bank/QuestionForm', [
            'title' => 'Tambah Soal — '.$batch->name,
            'batch' => $batch->only(['id', 'name']),
            'edit' => false,
            'question' => null,
            'typeOptions' => QuestionType::options(),
        ]);
    }

    public function storeQuestion(StoreQuestionRequest $request, QuestionBatch $batch): RedirectResponse
    {
        $question = $this->saveQuestion($request, $batch);

        $this->audit->log(action: 'question.created', subject: $question, description: "Soal bank ditambahkan ke \"{$batch->name}\".");

        return redirect()->route('admin.bank.show', $batch)->with('success', 'Soal berhasil ditambahkan.');
    }

    public function editQuestion(QuestionBatch $batch, Question $question): Response
    {
        $this->assertInBatch($batch, $question);
        $question->load(['options', 'matchingPairs']);

        return Inertia::render('Admin/Bank/QuestionForm', [
            'title' => 'Ubah Soal #'.$question->order,
            'batch' => $batch->only(['id', 'name']),
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
                    'label' => $o->label, 'option_text' => $o->option_text, 'is_correct' => (bool) $o->is_correct,
                ])->all(),
                'matching_pairs' => $question->matchingPairs->map(fn ($p) => [
                    'left_text' => $p->left_text, 'right_text' => $p->right_text,
                ])->all(),
            ],
            'typeOptions' => QuestionType::options(),
        ]);
    }

    public function updateQuestion(StoreQuestionRequest $request, QuestionBatch $batch, Question $question): RedirectResponse
    {
        $this->assertInBatch($batch, $question);
        $this->saveQuestion($request, $batch, $question);

        $this->audit->log(action: 'question.updated', subject: $question, description: "Soal bank pada \"{$batch->name}\" diubah.");

        return redirect()->route('admin.bank.show', $batch)->with('success', 'Soal berhasil diperbarui.');
    }

    public function destroyQuestion(QuestionBatch $batch, Question $question): RedirectResponse
    {
        $this->assertInBatch($batch, $question);
        $question->delete();

        return back()->with('success', 'Soal dihapus dari bank.');
    }

    private function assertInBatch(QuestionBatch $batch, Question $question): void
    {
        if ((int) $question->batch_id !== (int) $batch->id || $question->exam_id !== null) {
            abort(404);
        }
    }

    /**
     * Simpan soal bank + kunci (pola sama dengan QuestionController::saveQuestion).
     */
    private function saveQuestion(StoreQuestionRequest $request, QuestionBatch $batch, ?Question $existing = null): Question
    {
        $validated = $request->validated();
        $type = QuestionType::from($validated['type']);

        $data = [
            'type' => $type->value,
            'stimulus' => $validated['stimulus'] ?? null,
            'question_text' => $validated['question_text'],
            'media_url' => $validated['media_url'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ];

        if ($existing === null) {
            $data['order'] = (int) ($batch->questions()->whereNull('exam_id')->max('order') ?? 0) + 1;
            $question = $batch->questions()->create(array_merge($data, [
                'exam_id' => null,
                'class_id' => $batch->class_id,
            ]));
        } else {
            $data['order'] = (int) $validated['order'];
            $existing->update($data);
            $question = $existing;
        }

        $question->options()->delete();
        $question->matchingPairs()->delete();

        if ($type === QuestionType::Pg || $type === QuestionType::Pgk) {
            $order = 0;
            foreach ($validated['options'] ?? [] as $option) {
                if (trim((string) $option['option_text']) === '') {
                    continue;
                }
                Option::create([
                    'question_id' => $question->id,
                    'label' => $option['label'] ?? chr(ord('A') + $order),
                    'option_text' => $option['option_text'],
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'order' => $order++,
                ]);
            }
        }

        if ($type === QuestionType::Boolean) {
            foreach ($validated['options'] ?? [] as $option) {
                if (trim((string) $option['option_text']) === '') {
                    continue;
                }
                Option::create([
                    'question_id' => $question->id,
                    'label' => $option['label'],
                    'option_text' => $option['option_text'],
                    'is_correct' => (bool) ($option['is_correct'] ?? false),
                    'order' => $option['label'] === 'true' ? 0 : 1,
                ]);
            }
        }

        if ($type === QuestionType::Matching) {
            $order = 0;
            foreach ($validated['matching_pairs'] ?? [] as $pair) {
                if (trim((string) ($pair['left_text'] ?? '')) === '' || trim((string) ($pair['right_text'] ?? '')) === '') {
                    continue;
                }
                MatchingPair::create([
                    'question_id' => $question->id,
                    'left_text' => $pair['left_text'],
                    'right_text' => $pair['right_text'],
                    'order' => $order++,
                ]);
            }
        }

        return $question->refresh();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function classOptions(): array
    {
        return SchoolClass::query()->active()->ordered()->get()
            ->map(fn (SchoolClass $c) => ['value' => (int) $c->id, 'label' => $c->name])
            ->all();
    }
}

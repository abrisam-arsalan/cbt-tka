<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\AuditLogService;
use App\Services\ImportQuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionTemplateController extends Controller
{
    public function __construct(
        private readonly ImportQuestionService $imports,
        private readonly AuditLogService $audit,
    ) {}

    public function index(): Response
    {
        $templates = \App\Models\QuestionTemplate::query()
            ->with('creator')
            ->orderBy('question_type')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Templates/Index', [
            'title' => 'Template Soal',
            'templates' => $templates->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'description' => $t->description,
                'question_type' => $t->question_type->value,
                'question_type_label' => $t->question_type->label(),
                'has_custom_file' => $t->hasCustomFile(),
                'is_builtin' => (bool) $t->is_builtin,
                'is_active' => (bool) $t->is_active,
                'creator_name' => $t->creator?->name,
                'created_at' => $t->created_at?->toIso8601String(),
            ]),
            'typeOptions' => QuestionType::options(),
        ]);
    }

    /**
     * Unduh template bawaan per tipe soal (CSV).
     */
    public function download(string $type): BinaryFileResponse
    {
        $questionType = QuestionType::tryFrom($type);

        if ($questionType === null) {
            abort(404, 'Tipe soal tidak dikenal.');
        }

        $path = $this->imports->downloadTemplate($questionType);

        return response()
            ->download($path, 'template-soal-'.$questionType->value.'.csv')
            ->deleteFileAfterSend(true);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Templates/Form', [
            'title' => 'Tambah Template',
            'edit' => false,
            'template' => null,
            'typeOptions' => QuestionType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'question_type' => ['required', Rule::in(QuestionType::values())],
            'file' => ['nullable', 'file', 'mimes:csv,txt,xlsx,xls', 'max:2048'],
        ]);

        $filePath = null;

        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('templates', 'public');
        }

        $template = \App\Models\QuestionTemplate::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'question_type' => $validated['question_type'],
            'file_path' => $filePath,
            'is_active' => true,
            'is_builtin' => false,
            'created_by' => $request->user()->id,
        ]);

        $this->audit->log(
            action: 'template.created',
            subject: $template,
            description: "Template soal {$template->name} dibuat.",
        );

        return redirect()
            ->route('admin.templates.index')
            ->with('success', 'Template berhasil dibuat.');
    }

    public function edit(\App\Models\QuestionTemplate $template): Response
    {
        return Inertia::render('Admin/Templates/Form', [
            'title' => 'Edit Template: '.$template->name,
            'edit' => true,
            'template' => $template->only(['id', 'name', 'description', 'question_type', 'is_active']),
            'typeOptions' => QuestionType::options(),
        ]);
    }

    public function update(Request $request, \App\Models\QuestionTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'question_type' => ['required', Rule::in(QuestionType::values())],
            'file' => ['nullable', 'file', 'mimes:csv,txt,xlsx,xls', 'max:2048'],
        ]);

        if ($request->hasFile('file')) {
            if ($template->hasCustomFile()) {
                Storage::disk('public')->delete($template->file_path);
            }

            $validated['file_path'] = $request->file('file')->store('templates', 'public');
        }

        $before = $template->only(['name', 'question_type']);
        $template->update($validated);

        $this->audit->logChanges('template.updated', $template, $before, $template->only(['name', 'question_type']));

        return redirect()
            ->route('admin.templates.index')
            ->with('success', 'Template berhasil diperbarui.');
    }

    public function destroy(\App\Models\QuestionTemplate $template): RedirectResponse
    {
        if ((bool) $template->is_builtin) {
            return back()->with('error', 'Template bawaan tidak bisa dihapus.');
        }

        if ($template->hasCustomFile()) {
            Storage::disk('public')->delete($template->file_path);
        }

        $name = $template->name;
        $template->delete();

        $this->audit->log(
            action: 'template.deleted',
            subject: $template,
            description: "Template {$name} dihapus.",
        );

        return back()->with('success', "Template {$name} berhasil dihapus.");
    }
}

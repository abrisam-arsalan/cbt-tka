<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestionType;
use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Services\AuditLogService;
use App\Services\ImportQuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Impor soal dari CSV/XLSX.
 *
 * Alur dua langkah dengan penyimpanan server-side (session):
 *   1. preview : file diunggah + diparse. Baris valid disimpan ke session,
 *                BUKAN dikirim ke client lalu dikirim balik. Ini mencegah
 *                baris dimanipulasi client sebelum dieksekusi.
 *   2. execute : membaca baris valid dari session, menulis ke database,
 *                lalu menghapusnya dari session.
 */
class ImportController extends Controller
{
    private const SESSION_KEY = 'cbt.import.pending';

    public function __construct(
        private readonly ImportQuestionService $imports,
        private readonly AuditLogService $audit,
    ) {}

    public function show(Exam $exam): Response
    {
        return Inertia::render('Admin/Import/Show', [
            'title' => 'Impor Soal: '.$exam->title,
            'exam' => $exam->only(['id', 'title', 'status']),
            'typeOptions' => QuestionType::options(),
        ]);
    }

    public function preview(Request $request, Exam $exam): Response
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(QuestionType::values())],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ], [
            'file.mimes' => 'File harus berformat CSV atau XLSX.',
            'file.max' => 'Ukuran file maksimum 5 MB.',
        ]);

        $type = QuestionType::from($validated['type']);
        $parsed = $this->imports->parseFile($request->file('file'), $type);

        // Simpan baris valid di session; execute tidak menerima data dari client.
        $request->session()->put(self::SESSION_KEY, [
            'exam_id' => $exam->id,
            'type' => $type->value,
            'rows' => $parsed['valid'],
        ]);

        return Inertia::render('Admin/Import/Preview', [
            'title' => 'Pratinjau Impor',
            'exam' => $exam->only(['id', 'title', 'status']),
            'type' => $type->value,
            'type_label' => $type->label(),
            'validRows' => $parsed['valid'],
            'errorRows' => $parsed['errors'],
            'validCount' => count($parsed['valid']),
            'errorCount' => count($parsed['errors']),
        ]);
    }

    public function execute(Request $request, Exam $exam): RedirectResponse
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if ($pending === null) {
            return redirect()
                ->route('admin.exams.import.show', $exam)
                ->with('error', 'Sesi pratinjau impor sudah kedaluwarsa. Unggah ulang file.');
        }

        if ((int) $pending['exam_id'] !== (int) $exam->id) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()
                ->route('admin.exams.import.show', $exam)
                ->with('error', 'Data pratinjau tidak cocok dengan ujian ini. Unggah ulang file.');
        }

        $imported = $this->imports->importRows($exam, $pending['rows'], $request->user());

        $request->session()->forget(self::SESSION_KEY);

        return redirect()
            ->route('admin.exams.questions.index', $exam)
            ->with('success', "{$imported} soal berhasil diimpor.");
    }
}

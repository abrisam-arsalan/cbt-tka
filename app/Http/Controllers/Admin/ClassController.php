<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreClassRequest;
use App\Models\SchoolClass;
use App\Services\AuditLogService;
use App\Services\BulkImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClassController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly BulkImportService $imports,
    ) {}

    public function index(Request $request): Response
    {
        $classes = SchoolClass::query()
            ->withCount(['students'])
            ->ordered()
            ->get();

        return Inertia::render('Admin/Classes/Index', [
            'title' => 'Manajemen Kelas',
            'classes' => $classes->map(fn (SchoolClass $class) => [
                'id' => $class->id,
                'name' => $class->name,
                'grade' => $class->grade,
                'academic_year' => $class->academic_year,
                'description' => $class->description,
                'students_count' => (int) $class->students_count,
                'is_active' => (bool) $class->is_active,
            ]),
            'importErrors' => $request->session()->pull('import_errors', []),
        ]);
    }

    /**
     * Unduh template CSV untuk menambah banyak kelas sekaligus.
     */
    public function template(): BinaryFileResponse
    {
        $path = $this->imports->downloadClassTemplate();

        return response()->download($path, 'template-kelas.csv')->deleteFileAfterSend();
    }

    /**
     * Impor kelas dari file CSV/XLSX.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ], [
            'file.mimes' => 'File harus berformat CSV atau XLSX.',
            'file.max' => 'Ukuran file maksimum 5 MB.',
        ]);

        $result = $this->imports->importClasses($request->file('file'), $request->user());

        if ($result['errors'] !== []) {
            $request->session()->put('import_errors', $result['errors']);
        }

        $message = $result['imported'] > 0
            ? "{$result['imported']} kelas berhasil ditambahkan"
               .($result['errors'] !== [] ? ', '.count($result['errors'])." baris dilewati (lihat detail)." : '.')
            : 'Tidak ada kelas yang ditambahkan. Periksa kembali file Anda.';

        return redirect()
            ->route('admin.classes.index')
            ->with($result['imported'] > 0 ? 'success' : 'warning', $message);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Classes/Form', [
            'title' => 'Tambah Kelas',
            'edit' => false,
            'class' => null,
        ]);
    }

    public function store(StoreClassRequest $request): RedirectResponse
    {
        $class = SchoolClass::create($request->validated());

        $this->audit->log(
            action: 'class.created',
            subject: $class,
            description: "Kelas {$class->name} dibuat.",
        );

        return redirect()
            ->route('admin.classes.index')
            ->with('success', "Kelas {$class->name} berhasil dibuat.");
    }

    public function edit(SchoolClass $class): Response
    {
        return Inertia::render('Admin/Classes/Form', [
            'title' => 'Edit Kelas: '.$class->name,
            'edit' => true,
            'class' => $class->only(['id', 'name', 'grade', 'academic_year', 'description', 'is_active']),
        ]);
    }

    public function update(StoreClassRequest $request, SchoolClass $class): RedirectResponse
    {
        $before = $class->only(['name', 'grade', 'academic_year', 'is_active']);
        $class->update($request->validated());

        $this->audit->logChanges('class.updated', $class, $before, $class->only(['name', 'grade', 'academic_year', 'is_active']));

        return redirect()
            ->route('admin.classes.index')
            ->with('success', "Kelas {$class->name} berhasil diperbarui.");
    }

    public function destroy(SchoolClass $class): RedirectResponse
    {
        $studentsCount = $class->students()->count();

        if ($studentsCount > 0) {
            return back()->with('error', "Kelas {$class->name} masih memiliki {$studentsCount} siswa. Pindahkan siswa dulu sebelum menghapus kelas.");
        }

        $name = $class->name;
        $class->delete();

        $this->audit->log(
            action: 'class.deleted',
            subject: $class,
            description: "Kelas {$name} dihapus.",
        );

        return back()->with('success', "Kelas {$name} berhasil dihapus.");
    }
}

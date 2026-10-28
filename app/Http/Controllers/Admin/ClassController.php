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
        private readonly \App\Services\StudentSyncService $syncService,
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
     * Sinkronkan seluruh data kelas dari database Portal Panglima ke CBT.
     */
    public function sync(): RedirectResponse
    {
        $result = $this->syncService->syncClasses();

        if (!empty($result['errors'])) {
            return redirect()
                ->route('admin.classes.index')
                ->with('warning', 'Sinkronisasi kelas selesai dengan catatan: ' . implode('; ', $result['errors']));
        }

        if ($result['total'] === 0) {
            return redirect()
                ->route('admin.classes.index')
                ->with('warning', 'Tidak ada data kelas ditemukan di Portal Panglima.');
        }

        return redirect()
            ->route('admin.classes.index')
            ->with('success', "Berhasil mensinkronkan {$result['synced']} kelas dari Portal Panglima (Baru: {$result['created']}, Diperbarui: {$result['updated']}).");
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
     * Impor kelas dinonaktifkan di CBT — dikelola dari Portal Panglima.
     */
    public function import(Request $request): RedirectResponse
    {
        return redirect()
            ->route('admin.classes.index')
            ->with('warning', 'Impor data kelas dilakukan terpusat melalui Portal Panglima. Silakan kelola data kelas di Portal Panglima.');
    }

    /**
     * Tambah kelas dinonaktifkan di CBT — dikelola dari Portal Panglima.
     */
    public function create(): RedirectResponse
    {
        return redirect()
            ->route('admin.classes.index')
            ->with('warning', 'Penambahan kelas dikelola terpusat dari Portal Panglima. Silakan tambahkan kelas di Portal Panglima lalu lakukan sinkronisasi.');
    }

    public function store(StoreClassRequest $request): RedirectResponse
    {
        return redirect()
            ->route('admin.classes.index')
            ->with('warning', 'Penambahan kelas dikelola terpusat dari Portal Panglima. Silakan tambahkan kelas di Portal Panglima lalu lakukan sinkronisasi.');
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

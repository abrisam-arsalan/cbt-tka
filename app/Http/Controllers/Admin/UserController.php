<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\BulkImportService;
use App\Services\PinService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserController extends Controller
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly BulkImportService $imports,
        private readonly PinService $pins,
        private readonly \App\Services\StudentSyncService $syncService,
    ) {}

    public function index(Request $request): Response
    {
        // Menu Siswa khusus peserta didik; akun admin dikelola lewat Profil.
        $users = User::query()
            ->siswa()
            ->with('schoolClass')
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where('name', 'like', $term)->orWhere('username', 'like', $term);
            }))
            ->when($request->filled('class_id'), fn ($q) => $q->where('class_id', $request->integer('class_id')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'title' => 'Data Siswa',
            'users' => $users->through(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'class_id' => $user->class_id,
                'class_name' => $user->schoolClass?->name,
                'nisn' => $user->nisn,
                'is_active' => (bool) $user->is_active,
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ]),
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'class_id' => $request->input('class_id'),
            ],
            'classOptions' => SchoolClass::query()->active()->ordered()->get()
                ->map(fn (SchoolClass $c) => ['value' => (int) $c->id, 'label' => $c->name])
                ->all(),
            'importErrors' => $request->session()->pull('import_errors', []),
        ]);
    }

    /**
     * Unduh template CSV untuk menambah banyak akun siswa sekaligus.
     */
    public function template(): BinaryFileResponse
    {
        $path = $this->imports->downloadStudentTemplate();

        return response()->download($path, 'template-siswa.csv')->deleteFileAfterSend();
    }

    /**
     * Sinkronkan data siswa aktif dari Portal Panglima ke CBT.
     */
    public function sync(): RedirectResponse
    {
        $result = $this->syncService->sync();

        if (!empty($result['errors'])) {
            return redirect()
                ->route('admin.users.index')
                ->with('warning', 'Sinkronisasi selesai dengan catatan: ' . implode('; ', $result['errors']));
        }

        if ($result['total'] === 0) {
            return redirect()
                ->route('admin.users.index')
                ->with('warning', 'Tidak ada data siswa ditemukan di Portal Panglima.');
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Berhasil mensinkronkan {$result['synced']} akun siswa dari Portal Panglima (Baru: {$result['created']}, Diperbarui: {$result['updated']}).");
    }

    /**
     * Impor akun siswa dinonaktifkan di CBT — dikelola dari Portal Panglima.
     */
    public function import(Request $request): RedirectResponse
    {
        return redirect()
            ->route('admin.users.index')
            ->with('warning', 'Impor data siswa dilakukan terpusat melalui Portal Panglima. Silakan unggah di menu Impor Portal Panglima.');
    }

    /**
     * Tambah siswa dinonaktifkan di CBT — dikelola dari Portal Panglima.
     */
    public function create(): RedirectResponse
    {
        return redirect()
            ->route('admin.users.index')
            ->with('warning', 'Penambahan akun siswa dikelola terpusat dari Portal Panglima. Silakan gunakan tombol "Sinkronkan Siswa dari Portal".');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        return redirect()
            ->route('admin.users.index')
            ->with('warning', 'Penambahan akun siswa dikelola terpusat dari Portal Panglima. Silakan unggah di Portal lalu lakukan sinkronisasi.');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'title' => 'Edit Siswa: '.$user->name,
            'edit' => true,
            'user' => $user->only([
                'id', 'username', 'name', 'email', 'role',
                'class_id', 'nisn', 'phone', 'is_active',
            ]),
            'classes' => $this->classOptions(),
            'roleOptions' => UserRole::options(),
        ]);
    }

    public function update(StoreUserRequest $request, User $user): RedirectResponse
    {
        $data = $this->userData($request);

        // Password diisi => ganti PIN (hash + pin_cipher ikut diperbarui);
        // kosong => PIN lama dipertahankan.
        if (filled($data['password'] ?? null) && $data['password'] !== $user->username) {
            $this->pins->apply($user, $data['password']);
        } elseif (filled($data['password'] ?? null)) {
            unset($data['password']);

            return back()->withErrors(['password' => 'PIN tidak boleh sama dengan username.']);
        }

        unset($data['password']);

        $before = $user->only(array_keys($data));
        $user->update($data);

        $this->audit->logChanges('user.updated', $user, $before, $user->only(array_keys($data)));

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Siswa {$user->name} berhasil diperbarui.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === (int) $request->user()->id) {
            return back()->with('error', 'Anda tidak bisa menghapus akun sendiri.');
        }

        $activeAttempts = $user->attempts()->active()->count();

        if ($activeAttempts > 0) {
            return back()->with('error', "User {$user->name} masih memiliki {$activeAttempts} attempt aktif. Selesaikan dulu ujiannya.");
        }

        $name = $user->name;
        $user->delete();

        $this->audit->log(
            action: 'user.deleted',
            subject: $user,
            description: "User {$name} ({$user->username}) dihapus (soft delete).",
        );

        return back()->with('success', "User {$name} berhasil dihapus.");
    }

    /**
     * @return array<string, mixed>
     */
    private function userData(StoreUserRequest $request): array
    {
        $data = $request->validated();
        // Menu Siswa selalu membuat/mengubah peserta didik; role dipaksa siswa.
        $data['role'] = UserRole::Siswa->value;
        $data['is_active'] = $request->boolean('is_active', true);

        // Username login siswa = NISN bila tidak diisi manual.
        if (($data['username'] ?? '') === '' && ! empty($data['nisn'])) {
            $data['username'] = $data['nisn'];
        }

        if (array_key_exists('password', $data) && ($data['password'] === null || $data['password'] === '')) {
            $data['password'] = null;
        }

        return $data;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function classOptions(): array
    {
        return SchoolClass::query()
            ->active()
            ->ordered()
            ->get()
            ->map(fn (SchoolClass $class) => [
                'value' => (int) $class->id,
                'label' => $class->name.(($class->grade ?? '') !== '' ? " ({$class->grade})" : ''),
            ])
            ->all();
    }
}

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
     * Impor akun siswa dari file CSV/XLSX.
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:5120'],
        ], [
            'file.mimes' => 'File harus berformat CSV atau XLSX.',
            'file.max' => 'Ukuran file maksimum 5 MB.',
        ]);

        $result = $this->imports->importStudents($request->file('file'), $request->user());

        if ($result['errors'] !== []) {
            $request->session()->put('import_errors', $result['errors']);
        }

        $message = $result['imported'] > 0
            ? "{$result['imported']} akun siswa berhasil ditambahkan"
               .($result['errors'] !== [] ? ', '.count($result['errors'])." baris dilewati (lihat detail)." : '.')
            : 'Tidak ada akun yang ditambahkan. Periksa kembali file Anda.';

        return redirect()
            ->route('admin.users.index')
            ->with($result['imported'] > 0 ? 'success' : 'warning', $message);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'title' => 'Tambah Siswa',
            'edit' => false,
            'user' => null,
            'classes' => $this->classOptions(),
            'roleOptions' => UserRole::options(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $this->userData($request);

        // PIN kosong => digenerate otomatis agar setiap siswa punya PIN unik
        // yang tercetak di kartu ujiannya.
        $pin = filled($data['password'] ?? null)
            ? $data['password']
            : $this->pins->generate($data['username']);

        // Username jangan sampai sama dengan password.
        if ($pin === $data['username']) {
            $pin = $this->pins->generate($data['username']);
        }

        unset($data['password']);

        $user = new User($data);
        $this->pins->apply($user, $pin);
        $user->save();

        $this->audit->log(
            action: 'user.created',
            subject: $user,
            description: "Siswa {$user->username} dibuat"
                .(filled($request->input('password')) ? '.' : ' dengan PIN otomatis.'),
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', "Siswa {$user->name} berhasil dibuat. PIN login: {$pin} — pastikan tercetak di kartu ujian.");
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

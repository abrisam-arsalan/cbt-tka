<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly AuditLogService $audit) {}

    public function index(Request $request): Response
    {
        $role = $request->input('role');

        $users = User::query()
            ->withTrashed()
            ->with('schoolClass')
            ->when($role !== null && $role !== '' && $role !== 'all', fn ($q) => $q->where('role', $role))
            ->when($request->filled('search'), fn ($q) => $q->where(function ($q) use ($request) {
                $term = '%'.$request->string('search').'%';
                $q->where('name', 'like', $term)->orWhere('username', 'like', $term);
            }))
            ->orderBy('role')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'title' => 'Manajemen User',
            'users' => $users->through(fn (User $user) => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'class_id' => $user->class_id,
                'class_name' => $user->schoolClass?->name,
                'nisn' => $user->nisn,
                'is_active' => (bool) $user->is_active,
                'deleted_at' => $user->deleted_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
            ]),
            'filters' => [
                'role' => $role ?? 'all',
                'search' => (string) $request->input('search', ''),
            ],
            'roleOptions' => array_merge(
                [['value' => 'all', 'label' => 'Semua Role']],
                UserRole::options(),
            ),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'title' => 'Tambah User',
            'edit' => false,
            'user' => null,
            'classes' => $this->classOptions(),
            'roleOptions' => UserRole::options(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create($this->userData($request));

        $this->audit->log(
            action: 'user.created',
            subject: $user,
            description: "User {$user->username} dibuat.",
        );

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} berhasil dibuat.");
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'title' => 'Edit User: '.$user->name,
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

        if (($data['password'] ?? null) === null) {
            unset($data['password']);
        }

        $before = $user->only(array_keys($data));
        $user->update($data);

        $this->audit->logChanges('user.updated', $user, $before, $user->only(array_keys($data)));

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} berhasil diperbarui.");
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
        $data['is_active'] = $request->boolean('is_active', true);

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

<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentSyncService
{
    public function __construct(
        private readonly AuditLogService $audit
    ) {}

    /**
     * Sinkronkan data siswa aktif dari Portal Panglima ke CBT TKA.
     *
     * @return array{total: int, synced: int, created: int, updated: int, errors: array<string>}
     */
    public function sync(): array
    {
        $result = [
            'total' => 0,
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        try {
            $portalStudents = DB::select("
                SELECT u.id, u.username, u.nama, u.email, u.password_hash, u.pin_cipher, u.nisn, u.is_active,
                       c.name AS class_name, c.tingkat
                FROM panglima.users u
                LEFT JOIN panglima.classes c ON c.id = u.class_id
                WHERE u.role = 'siswa' AND u.deleted_at IS NULL
            ");
        } catch (\Throwable $e) {
            $result['errors'][] = 'Koneksi ke database panglima gagal: ' . $e->getMessage();
            return $result;
        }

        $result['total'] = count($portalStudents);
        if ($result['total'] === 0) {
            return $result;
        }

        // Sinkronkan seluruh master kelas terlebih dahulu
        $this->syncClasses();

        foreach ($portalStudents as $row) {
            try {
                // 1. Resolve / Create Class
                $classId = null;
                if (!empty($row->class_name)) {
                    $schoolClass = SchoolClass::firstOrCreate(
                        ['name' => $row->class_name],
                        [
                            'grade' => (string) ($row->tingkat ?? '7'),
                            'academic_year' => '2026/2027',
                            'is_active' => true,
                        ]
                    );
                    $classId = $schoolClass->id;
                }

                // 2. Resolve User by username (which is NISN)
                $username = !empty($row->nisn) ? $row->nisn : $row->username;
                $user = User::withTrashed()->where('username', $username)->first();

                $isNew = false;
                if (! $user) {
                    $user = new User();
                    $user->username = $username;
                    $isNew = true;
                } elseif ($user->trashed()) {
                    $user->restore();
                }

                $user->name = $row->nama;
                $user->email = $row->email ?: null;
                $user->password = $row->password_hash;
                $user->role = UserRole::Siswa;
                $user->class_id = $classId;
                $user->nisn = $username;
                $user->is_active = (bool) $row->is_active;

                // plain PIN tersimpan di pin_cipher portal, diset agar otomatis dienkripsi APP_KEY oleh cast
                if (!empty($row->pin_cipher)) {
                    $user->forceFill(['pin_cipher' => $row->pin_cipher]);
                }

                $user->save();

                if ($isNew) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
                $result['synced']++;
            } catch (\Throwable $e) {
                $msg = "Gagal memproses siswa {$row->username}: " . $e->getMessage();
                $result['errors'][] = $msg;
                Log::error($msg);
            }
        }

        $this->audit->log(
            action: 'users.synced_portal',
            subject: null,
            description: "Sinkronisasi {$result['synced']} akun siswa dari Portal Panglima."
        );

        return $result;
    }

    /**
     * Sinkronkan seluruh data kelas dari Portal Panglima ke CBT TKA.
     *
     * @return array{total: int, synced: int, created: int, updated: int, errors: array<string>}
     */
    public function syncClasses(): array
    {
        $result = [
            'total' => 0,
            'synced' => 0,
            'created' => 0,
            'updated' => 0,
            'errors' => [],
        ];

        try {
            $portalClasses = DB::select("
                SELECT c.id, c.name, c.tingkat, c.is_active, c.description, ay.name AS academic_year
                FROM panglima.classes c
                LEFT JOIN panglima.academic_years ay ON ay.id = c.academic_year_id
                WHERE c.is_active = 1
            ");
        } catch (\Throwable $e) {
            $result['errors'][] = 'Koneksi ke database panglima gagal: ' . $e->getMessage();
            return $result;
        }

        $result['total'] = count($portalClasses);
        if ($result['total'] === 0) {
            return $result;
        }

        foreach ($portalClasses as $row) {
            try {
                $schoolClass = SchoolClass::where('name', $row->name)->first();
                $isNew = false;
                if (! $schoolClass) {
                    $schoolClass = new SchoolClass();
                    $schoolClass->name = $row->name;
                    $isNew = true;
                }

                $schoolClass->grade = (string) ($row->tingkat ?? '7');
                $schoolClass->academic_year = $row->academic_year ?: '2026/2027';
                $schoolClass->description = $row->description;
                $schoolClass->is_active = (bool) $row->is_active;
                $schoolClass->save();

                if ($isNew) {
                    $result['created']++;
                } else {
                    $result['updated']++;
                }
                $result['synced']++;
            } catch (\Throwable $e) {
                $msg = "Gagal memproses kelas {$row->name}: " . $e->getMessage();
                $result['errors'][] = $msg;
                Log::error($msg);
            }
        }

        $this->audit->log(
            action: 'classes.synced_portal',
            subject: null,
            description: "Sinkronisasi {$result['synced']} kelas dari Portal Panglima."
        );

        return $result;
    }
}

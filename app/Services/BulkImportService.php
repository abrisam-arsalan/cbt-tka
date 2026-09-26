<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\TabularReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Impor massal data master (Kelas & Siswa) dari file CSV/XLSX.
 *
 * Tiap baris divalidasi; baris valid ditulis ke database, baris error
 * dikumpulkan untuk ditampilkan kembali ke admin. Parsing memakai CSV native
 * dan PhpSpreadsheet (bukan paket Excel Laravel) agar tidak bergantung pada
 * kelas yang tidak tersedia di versi paket saat ini.
 */
class BulkImportService
{
    public function __construct(
        private readonly AuditLogService $audit,
        private readonly PinService $pins,
    ) {}

    // ------------------------------------------------------------------
    // KELAS
    // ------------------------------------------------------------------

    public function downloadClassTemplate(): string
    {
        $rows = [
            ['nama', 'tingkat', 'tahun_ajaran', 'deskripsi', 'aktif'],
            ['VII-A', 'VII', '2026/2027', 'Kelas tujuh A', 1],
            ['VII-B', 'VII', '2026/2027', 'Kelas tujuh B', 1],
            ['VIII-A', 'VIII', '2026/2027', 'Kelas delapan A', 1],
        ];

        return $this->writeCsv($rows, 'cbt_kelas_tpl_');
    }

    /**
     * @return array{imported: int, errors: array<int, array<string, mixed>>}
     */
    public function importClasses(UploadedFile $file, User $actor): array
    {
        $rows = $this->readRows($file);
        $errors = [];
        $seen = [];
        $imported = 0;

        DB::transaction(function () use ($rows, $actor, &$errors, &$seen, &$imported) {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2; // +1 header, +1 basis-1
                $normalized = $this->normalizeClassRow($row);
                $messages = $this->validateClassRow($normalized, $seen);

                if ($messages !== []) {
                    $errors[] = ['row' => $rowNumber, 'raw' => $normalized, 'errors' => $messages];

                    continue;
                }

                $seen[strtolower($normalized['name'].'|'.strtolower((string) $normalized['academic_year']))] = true;

                SchoolClass::create([
                    'name' => $normalized['name'],
                    'grade' => $normalized['grade'],
                    'academic_year' => $normalized['academic_year'],
                    'description' => $normalized['description'],
                    'is_active' => $normalized['is_active'],
                ]);
                $imported++;
            }

            if ($imported > 0) {
                $this->audit->log(
                    action: 'class.bulk_imported',
                    description: "{$imported} kelas diimpor dari file.",
                    meta: ['count' => $imported, 'errors' => count($errors)],
                );
            }
        });

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeClassRow(array $row): array
    {
        return [
            'name' => trim((string) $this->coalesce($row, ['nama', 'name', 'kelas'])),
            'grade' => $this->nullableTrim($this->coalesce($row, ['tingkat', 'grade', 'jenjang'])),
            'academic_year' => $this->nullableTrim($this->coalesce($row, ['tahun_ajaran', 'tahun', 'academic_year'])),
            'description' => $this->nullableTrim($this->coalesce($row, ['deskripsi', 'description', 'keterangan'])),
            'is_active' => $this->parseBool($this->coalesce($row, ['aktif', 'is_active', 'status']) ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, bool>  $seen
     * @return array<int, string>
     */
    private function validateClassRow(array $row, array $seen): array
    {
        $messages = [];

        if ($row['name'] === '') {
            $messages[] = 'Kolom "nama" wajib diisi.';

            return $messages;
        }

        if (mb_strlen($row['name']) > 64) {
            $messages[] = 'Nama kelas maksimal 64 karakter.';
        }

        $key = strtolower($row['name'].'|'.strtolower((string) $row['academic_year']));

        if (isset($seen[$key])) {
            $messages[] = 'Nama kelas duplikat di dalam file (tahun ajaran sama).';
        }

        $duplicate = SchoolClass::query()
            ->whereRaw('LOWER(name) = ?', [strtolower($row['name'])])
            ->when(
                $row['academic_year'] === null,
                fn ($q) => $q->whereNull('academic_year'),
                fn ($q) => $q->where('academic_year', $row['academic_year']),
            )
            ->exists();

        if ($duplicate) {
            $messages[] = 'Kelas dengan nama itu sudah ada pada tahun ajaran ini.';
        }

        return $messages;
    }

    // ------------------------------------------------------------------
    // SISWA
    // ------------------------------------------------------------------

    public function downloadStudentTemplate(): string
    {
        // username dikosongkan => otomatis memakai NISN.
        // pin dikosongkan => sistem generate PIN 6 digit acak (tercetak di kartu).
        $rows = [
            ['username', 'nama', 'nisn', 'email', 'kelas', 'pin', 'aktif'],
            ['', 'Budi Santoso', '0011223344', '', 'VII-A', '', 1],
            ['', 'Siti Aminah', '0011223345', '', 'VII-A', '', 1],
        ];

        return $this->writeCsv($rows, 'cbt_siswa_tpl_');
    }

    /**
     * @return array{imported: int, errors: array<int, array<string, mixed>>}
     */
    public function importStudents(UploadedFile $file, User $actor): array
    {
        $rows = $this->readRows($file);
        $errors = [];
        $seenUsernames = [];
        $seenNisn = [];
        $imported = 0;

        DB::transaction(function () use ($rows, $actor, &$errors, &$seenUsernames, &$seenNisn, &$imported) {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;
                $normalized = $this->normalizeStudentRow($row);

                // Username login = NISN bila kolom username dikosongkan.
                if ($normalized['username'] === '' && $normalized['nisn'] !== null) {
                    $normalized['username'] = $normalized['nisn'];
                }

                $messages = $this->validateStudentRow($normalized, $seenUsernames, $seenNisn);

                if ($messages !== []) {
                    $errors[] = ['row' => $rowNumber, 'raw' => $normalized, 'errors' => $messages];

                    continue;
                }

                $classId = null;

                if ($normalized['class_name'] !== null) {
                    $class = SchoolClass::query()
                        ->whereRaw('LOWER(name) = ?', [strtolower($normalized['class_name'])])
                        ->orderBy('id')
                        ->first();
                    $classId = $class?->id;
                }

                $username = strtolower($normalized['username']);
                $seenUsernames[$username] = true;

                if ($normalized['nisn'] !== null) {
                    $seenNisn[$normalized['nisn']] = true;
                }

                // PIN: pakai yang diisi bila valid, kalau kosong generate acak
                // 6 digit yang dijamin beda dari username.
                $pin = $normalized['password'] !== ''
                    ? $normalized['password']
                    : $this->pins->generate($normalized['username']);

                if ($pin === $normalized['username']) {
                    $pin = $this->pins->generate($normalized['username']);
                }

                $user = new User([
                    'username' => $normalized['username'],
                    'name' => $normalized['name'],
                    'email' => $normalized['email'],
                    'nisn' => $normalized['nisn'],
                    'role' => UserRole::Siswa,
                    'class_id' => $classId,
                    'is_active' => $normalized['is_active'],
                ]);
                $this->pins->apply($user, $pin);
                $user->save();

                $imported++;
            }

            if ($imported > 0) {
                $this->audit->log(
                    action: 'user.bulk_imported',
                    description: "{$imported} akun siswa diimpor dari file.",
                    meta: ['count' => $imported, 'errors' => count($errors)],
                );
            }
        });

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeStudentRow(array $row): array
    {
        return [
            'username' => trim((string) $this->coalesce($row, ['username', 'user', 'login'])),
            'name' => trim((string) $this->coalesce($row, ['nama', 'name', 'nama_lengkap'])),
            'nisn' => $this->nullableTrim($this->coalesce($row, ['nisn', 'nis', 'induk'])),
            'email' => $this->nullableTrim($this->coalesce($row, ['email', 'surel'])),
            'class_name' => $this->nullableTrim($this->coalesce($row, ['kelas', 'class', 'school_class'])),
            'password' => trim((string) ($this->coalesce($row, ['password', 'sandi', 'pass', 'pin']) ?? '')),
            'is_active' => $this->parseBool($this->coalesce($row, ['aktif', 'is_active', 'status']) ?? true),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, bool>  $seenUsernames
     * @param  array<string, bool>  $seenNisn
     * @return array<int, string>
     */
    private function validateStudentRow(array $row, array $seenUsernames, array $seenNisn): array
    {
        $messages = [];

        if ($row['username'] === '') {
            $messages[] = 'Username & NISN kosong — username login memakai NISN, isi salah satu.';
        } elseif (mb_strlen($row['username']) > 64) {
            $messages[] = 'Username maksimal 64 karakter.';
        } else {
            $username = strtolower($row['username']);

            if (isset($seenUsernames[$username])) {
                $messages[] = 'Username duplikat di dalam file.';
            } elseif (User::withTrashed()->whereRaw('LOWER(username) = ?', [$username])->exists()) {
                $messages[] = 'Username sudah dipakai akun lain.';
            }
        }

        if ($row['name'] === '') {
            $messages[] = 'Kolom "nama" wajib diisi.';
        }

        if ($row['email'] !== null && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
            $messages[] = 'Format email tidak valid.';
        } elseif ($row['email'] !== null && User::whereRaw('LOWER(email) = ?', [strtolower($row['email'])])->exists()) {
            $messages[] = 'Email sudah dipakai akun lain.';
        }

        if ($row['nisn'] !== null) {
            if (mb_strlen($row['nisn']) > 32) {
                $messages[] = 'NISN maksimal 32 karakter.';
            } elseif (isset($seenNisn[$row['nisn']])) {
                $messages[] = 'NISN duplikat di dalam file.';
            } elseif (User::withTrashed()->where('nisn', $row['nisn'])->exists()) {
                $messages[] = 'NISN sudah terdaftar di akun lain.';
            }
        }

        // PIN: boleh kosong (digenerate otomatis); kalau diisi harus angka
        // 4-8 digit dan berbeda dari username.
        if ($row['password'] !== '') {
            if (! preg_match('/^\d{4,8}$/', $row['password'])) {
                $messages[] = 'PIN harus 4-8 digit angka, atau kosongkan agar digenerate otomatis.';
            } elseif ($row['password'] === $row['username']) {
                $messages[] = 'PIN tidak boleh sama dengan username.';
            }
        }

        return $messages;
    }

    // ------------------------------------------------------------------
    // Helper bersama
    // ------------------------------------------------------------------

    /**
     * Baca file CSV/TXT/XLSX/XLS menjadi daftar baris asosiatif (header -> nilai).
     * Header dinormalisasi ke huruf kecil agar cocok dengan pemetaan kolom.
     *
     * @return array<int, array<string, mixed>>
     */
    private function readRows(UploadedFile $file): array
    {
        $path = $file->getPathname();
        $ext = strtolower($file->getClientOriginalExtension());

        $raw = in_array($ext, ['xlsx', 'xls'], true)
            ? $this->readSpreadsheet($path)
            : $this->readCsv($path);

        if ($raw === []) {
            return [];
        }

        $header = array_shift($raw);
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $rows = [];

        foreach ($raw as $line) {
            // Lewati baris yang seluruh selnya kosong.
            $filled = array_filter($line, fn ($v) => $v !== null && trim((string) $v) !== '');

            if ($filled === []) {
                continue;
            }

            $assoc = [];
            foreach ($header as $i => $key) {
                if ($key !== '') {
                    $assoc[$key] = $line[$i] ?? null;
                }
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        // Excel locale ID menyimpan CSV berpemisah ';' — deteksi otomatis.
        $delimiter = TabularReader::sniffDelimiter($path);
        $rows = [];
        $first = true;

        while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($first) {
                // Buang BOM UTF-8 pada header bila ada.
                if ($data !== []) {
                    $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]);
                }
                $first = false;
            }
            $rows[] = $data;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function readSpreadsheet(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        return $sheet->toArray(null, true, false, false);
    }

    /**
     * Tulis baris menjadi file CSV sementara untuk diunduh.
     *
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function writeCsv(array $rows, string $prefix): string
    {
        $tmp = tempnam(sys_get_temp_dir(), $prefix).'.csv';

        $handle = fopen($tmp, 'wb');
        // BOM UTF-8 supaya Excel membaca karakter non-ASCII dengan benar.
        fwrite($handle, "\xEF\xBB\xBF");

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return $tmp;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function coalesce(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    private function nullableTrim(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            return in_array($value, ['1', 'true', 'ya', 'yes', 'aktif', 'y'], true);
        }

        return (bool) $value;
    }
}

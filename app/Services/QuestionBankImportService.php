<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\MatchingPair;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionBatch;
use App\Models\SchoolClass;
use App\Models\User;
use App\Support\TabularReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Impor massal Bank Soal dari SATU file yang boleh berisi SEMUA jenis soal
 * (pg, pgk, boolean, matching) sekaligus. Kolom `jenis` pada tiap baris
 * menentukan bagaimana baris itu diparse; kolom `kelas` memberi kelas per soal.
 *
 * Hasil impor = satu QuestionBatch (nama bank + tanggal YYMM) berisi soal-soal
 * bank (exam_id null). Saat ujian dibuat, soal ini disalin ke ujian, sehingga
 * mengubah bank tidak memengaruhi ujian yang sudah memakai isinya.
 */
class QuestionBankImportService
{
    /** Kolom template terpadu. */
    public const COLUMNS = [
        'jenis', 'kelas', 'stimulus', 'pertanyaan', 'media_url',
        'opsi_a', 'opsi_b', 'opsi_c', 'opsi_d', 'opsi_e', 'kunci',
        'kiri_1', 'kanan_1', 'kiri_2', 'kanan_2', 'kiri_3', 'kanan_3',
        'kiri_4', 'kanan_4', 'kiri_5', 'kanan_5', 'aktif',
    ];

    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Nama batch dengan suffix tanggal YYMM (mis. "Matematika 2609").
     */
    public function buildBatchName(string $name, ?\DateTimeInterface $when = null): string
    {
        $name = trim($name);
        $when ??= now();

        return $name.' '.$when->format('ym');
    }

    public function downloadTemplate(): string
    {
        $rows = [
            self::COLUMNS,
            ['pg', '7A', '', 'Ibu kota Indonesia adalah...', '', 'Jakarta', 'Surabaya', 'Bandung', 'Medan', '', 'A', '', '', '', '', '', '', '', '', '', '', 1],
            ['pgk', '7A', '', 'Manakah bilangan prima?', '', '2', '3', '4', '5', '9', 'A,B,D', '', '', '', '', '', '', '', '', '', '', 1],
            ['boolean', '7A', '', 'Matahari terbit di timur.', '', '', '', '', '', '', 'Benar', '', '', '', '', '', '', '', '', '', '', 1],
            ['matching', '7A', '', 'Pasangkan negara dengan ibu kotanya.', '', '', '', '', '', '', '', 'Indonesia', 'Jakarta', 'Jepang', 'Tokyo', 'Prancis', 'Paris', 'Italia', 'Roma', '', '', 1],
        ];

        return $this->writeCsv($rows, 'cbt_bank_tpl_');
    }

    /**
     * Parse, validasi, dan tulis satu batch bank soal.
     *
     * Format file: CSV/XLSX biasa, ATAU ZIP berisi satu file CSV/XLSX
     * (template) + gambar-gambar yang dirujuk kolom `gambar`/`media_url`
     * (mis. "peta.png" atau "gambar/peta.png" di dalam ZIP).
     *
     * @return array{batch: ?QuestionBatch, imported: int, errors: array<int, array<string, mixed>>}
     */
    public function import(UploadedFile $file, string $name, ?int $classId, User $actor): array
    {
        $imageMap = [];
        $extractDir = null;

        if (strtolower($file->getClientOriginalExtension()) === 'zip') {
            [$rows, $extractDir] = $this->readZip($file);
            $imageMap = $this->collectZipImages($extractDir);
        } else {
            $rows = TabularReader::rows($file);
        }

        try {
            return $this->importRows($rows, $imageMap, $name, $classId, $actor);
        } finally {
            if ($extractDir !== null && is_dir($extractDir)) {
                $this->deleteDirectory($extractDir);
            }
        }
    }

    /**
     * Ekstrak ZIP dan baca file tabular di dalamnya.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: string}
     */
    private function readZip(UploadedFile $file): array
    {
        $zip = new \ZipArchive;

        if ($zip->open($file->getPathname()) !== true) {
            throw new \RuntimeException('File ZIP tidak dapat dibuka.');
        }

        $extractDir = storage_path('app/tmp/bank-impor-'.bin2hex(random_bytes(6)));
        mkdir($extractDir, 0755, true);
        $zip->extractTo($extractDir);
        $zip->close();

        // Cari CSV/XLSX: di root hasil ekstrak dulu, lalu satu level subfolder.
        $candidates = array_merge(
            glob($extractDir.'/*.{csv,txt,xlsx,xls}', GLOB_BRACE) ?: [],
            glob($extractDir.'/*/*.{csv,txt,xlsx,xls}', GLOB_BRACE) ?: [],
        );

        if ($candidates === []) {
            throw new \RuntimeException('ZIP harus memuat satu file template CSV/XLSX.');
        }

        $tabular = $candidates[0];
        $rows = TabularReader::rowsFromPath($tabular, strtolower(pathinfo($tabular, PATHINFO_EXTENSION)));

        return [$rows, $extractDir];
    }

    /**
     * Peta nama-file gambar (huruf kecil, dengan & tanpa path) -> path absolut.
     *
     * @return array<string, string>
     */
    private function collectZipImages(string $extractDir): array
    {
        $map = [];
        $allowed = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($extractDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $splFile) {
            if (! $splFile->isFile()) {
                continue;
            }

            $ext = strtolower($splFile->getExtension());

            if (! in_array($ext, $allowed, true)) {
                continue;
            }

            $relative = strtolower(trim(str_replace('\\', '/', substr($splFile->getPathname(), strlen($extractDir) + 1)), '/'));
            $map[$relative] = $splFile->getPathname();
            $map[basename($relative)] ??= $splFile->getPathname();
        }

        return $map;
    }

    /**
     * Simpan gambar dari ZIP ke disk public; kembalikan URL-nya.
     * Penamaan berdasarkan hash isi => impor ulang file yang sama tidak menduplikasi.
     */
    private function storeZipImage(string $sourcePath): ?string
    {
        $ext = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $hash = sha1_file($sourcePath);

        if ($hash === false || filesize($sourcePath) > 8 * 1024 * 1024) {
            return null;
        }

        $relative = 'soal/impor/'.substr($hash, 0, 2).'/'.$hash.'.'.$ext;

        if (! Storage::disk('public')->exists($relative)) {
            $target = Storage::disk('public')->path($relative);
            @mkdir(dirname($target), 0755, true);

            if (! copy($sourcePath, $target)) {
                return null;
            }
        }

        return Storage::disk('public')->url($relative);
    }

    private function deleteDirectory(string $dir): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, string>  $imageMap
     * @return array{batch: ?QuestionBatch, imported: int, errors: array<int, array<string, mixed>>}
     */
    private function importRows(array $rows, array $imageMap, string $name, ?int $classId, User $actor): array
    {

        // Peta kelas (nama kecil -> id) untuk resolusi kolom `kelas`.
        $classMap = SchoolClass::query()->get()
            ->mapWithKeys(fn (SchoolClass $c) => [strtolower(trim($c->name)) => (int) $c->id])
            ->all();

        $errors = [];
        $valid = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $normalized = $this->normalizeRow($row, $classMap, $classId);
            $messages = $this->validateRow($normalized);

            // Kolom gambar: URL http(s) dipakai apa adanya; nama file
            // diresolved dari ZIP (bila impor lewat ZIP).
            $media = trim((string) ($normalized['media_url'] ?? ''));

            if ($media !== '' && ! preg_match('#^https?://#i', $media)) {
                $media = ltrim(str_replace('\\', '/', $media), '/');
                $source = $imageMap[strtolower($media)] ?? $imageMap[basename(strtolower($media))] ?? null;

                if ($source === null) {
                    $messages[] = $imageMap === []
                        ? "Gambar \"{$media}\" tidak ditemukan — unggah ZIP berisi template + file gambar tsb., atau isi kolom dengan URL http(s)."
                        : "Gambar \"{$media}\" tidak ada di dalam ZIP.";
                } else {
                    $url = $this->storeZipImage($source);
                    $normalized['media_url'] = $url;

                    if ($url === null) {
                        $messages[] = "Gambar \"{$media}\" gagal disimpan (korup atau melebihi 8 MB).";
                    }
                }
            }

            if ($messages !== []) {
                $errors[] = ['row' => $rowNumber, 'raw' => $normalized, 'errors' => $messages];

                continue;
            }

            $valid[] = $normalized;
        }

        if ($valid === []) {
            return ['batch' => null, 'imported' => 0, 'errors' => $errors];
        }

        $batch = null;
        $imported = 0;

        DB::transaction(function () use ($name, $classId, $actor, $valid, &$batch, &$imported) {
            $batch = QuestionBatch::create([
                'name' => $this->buildBatchName($name),
                'class_id' => $classId,
                'created_by' => $actor->id,
            ]);

            $order = 0;

            foreach ($valid as $row) {
                $order++;
                $question = Question::create([
                    'exam_id' => null,
                    'batch_id' => $batch->id,
                    'class_id' => $row['class_id'],
                    'type' => $row['type']->value,
                    'stimulus' => $row['stimulus'],
                    'question_text' => $row['question_text'],
                    'media_url' => $row['media_url'],
                    'order' => $order,
                    'is_active' => $row['is_active'],
                ]);

                $this->writeKey($question, $row);
                $imported++;
            }

            $this->audit->log(
                action: 'question_batch.imported',
                subject: $batch,
                description: "Bank \"{$batch->name}\" dibuat dengan {$imported} soal.",
                meta: ['count' => $imported, 'errors' => count($valid) === 0 ? 0 : null],
            );
        });

        return ['batch' => $batch, 'imported' => $imported, 'errors' => $errors];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $classMap
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, array $classMap, ?int $defaultClassId): array
    {
        $jenisRaw = strtolower(trim((string) $this->coalesce($row, ['jenis', 'type', 'tipe'])));
        $type = $this->resolveType($jenisRaw);

        $kelasRaw = trim((string) ($this->coalesce($row, ['kelas', 'class']) ?? ''));
        $classId = $defaultClassId;
        $classError = null;

        if ($kelasRaw !== '') {
            if (isset($classMap[$kelasLower = strtolower($kelasRaw)])) {
                $classId = $classMap[$kelasLower];
            } else {
                $classError = "Kelas \"{$kelasRaw}\" tidak ditemukan. Buat kelas itu dulu.";
            }
        }

        $out = [
            'type' => $type,
            'jenis_raw' => $jenisRaw,
            'class_id' => $classId,
            'class_error' => $classError,
            'stimulus' => $this->nullableTrim($this->coalesce($row, ['stimulus', 'wacana', 'bacaan'])),
            'question_text' => trim((string) $this->coalesce($row, ['pertanyaan', 'question_text', 'soal', 'text'])),
            'media_url' => $this->nullableTrim($this->coalesce($row, ['media_url', 'gambar', 'media'])),
            'is_active' => $this->parseBool($this->coalesce($row, ['aktif', 'is_active', 'status']) ?? true),
            'correct' => strtoupper(trim((string) $this->coalesce($row, ['kunci', 'correct', 'jawaban']))),
        ];

        foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
            $out['option_'.$letter] = $this->nullableTrim($this->coalesce($row, ['opsi_'.$letter, 'option_'.$letter, strtoupper($letter)]));
        }

        for ($i = 1; $i <= 5; $i++) {
            $out['left_'.$i] = $this->nullableTrim($this->coalesce($row, ['kiri_'.$i, 'left_'.$i, 'premis_'.$i]));
            $out['right_'.$i] = $this->nullableTrim($this->coalesce($row, ['kanan_'.$i, 'right_'.$i, 'jawaban_'.$i]));
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>
     */
    private function validateRow(array $row): array
    {
        $messages = [];

        if ($row['type'] === null) {
            $messages[] = 'Kolom "jenis" tidak dikenali (pakai: pg, pgk, boolean, matching).';
        }

        if ($row['class_error'] !== null) {
            $messages[] = $row['class_error'];
        }

        if ($row['question_text'] === '') {
            $messages[] = 'Kolom "pertanyaan" wajib diisi.';
        }

        $type = $row['type'];

        if ($type === QuestionType::Pg || $type === QuestionType::Pgk) {
            $provided = [];
            foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
                if (($row['option_'.$letter] ?? null) !== null) {
                    $provided[strtoupper($letter)] = $row['option_'.$letter];
                }
            }

            if (count($provided) < 2) {
                $messages[] = 'Minimal dua opsi (opsi_a..opsi_e) harus diisi.';
            }

            if ($row['correct'] === '') {
                $messages[] = 'Kolom "kunci" wajib diisi.';
            } else {
                $letters = array_values(array_filter(array_map('trim', explode(',', $row['correct'])), fn ($l) => $l !== ''));

                if ($type === QuestionType::Pg && count($letters) !== 1) {
                    $messages[] = 'Soal PG harus punya tepat satu kunci.';
                }

                if ($type === QuestionType::Pgk && count($letters) < 1) {
                    $messages[] = 'Soal PGK harus punya minimal satu kunci.';
                }

                foreach ($letters as $letter) {
                    if (! array_key_exists($letter, $provided)) {
                        $messages[] = "Kunci '{$letter}' merujuk opsi yang tidak diisi.";
                    }
                }
            }
        }

        if ($type === QuestionType::Boolean) {
            if (! in_array(strtolower($row['correct']), ['benar', 'salah', 'true', 'false', '1', '0'], true)) {
                $messages[] = 'Kunci boolean harus Benar/Salah.';
            }
        }

        if ($type === QuestionType::Matching) {
            $pairs = 0;
            for ($i = 1; $i <= 5; $i++) {
                $left = $row['left_'.$i] ?? null;
                $right = $row['right_'.$i] ?? null;
                if ($left !== null && $right !== null) {
                    $pairs++;
                } elseif ($left !== null || $right !== null) {
                    $messages[] = "Pasangan ke-{$i} tidak lengkap (kiri & kanan harus sepasang).";
                }
            }
            if ($pairs < 2) {
                $messages[] = 'Soal menjodohkan minimal dua pasangan.';
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function writeKey(Question $question, array $row): void
    {
        $type = $question->type;

        if ($type === QuestionType::Pg || $type === QuestionType::Pgk) {
            $letters = array_map('trim', explode(',', $row['correct']));
            $letters = array_values(array_filter($letters, fn ($l) => $l !== ''));
            $order = 0;

            foreach (['a', 'b', 'c', 'd', 'e'] as $letter) {
                $text = $row['option_'.$letter] ?? null;
                if ($text === null) {
                    continue;
                }

                Option::create([
                    'question_id' => $question->id,
                    'label' => strtoupper($letter),
                    'option_text' => $text,
                    'is_correct' => in_array(strtoupper($letter), $letters, true),
                    'order' => $order++,
                ]);
            }
        }

        if ($type === QuestionType::Boolean) {
            $answerIsTrue = in_array(strtolower($row['correct']), ['benar', 'true', '1'], true);
            foreach (['true' => 'Benar', 'false' => 'Salah'] as $value => $text) {
                Option::create([
                    'question_id' => $question->id,
                    'label' => $value,
                    'option_text' => $text,
                    'is_correct' => ($value === 'true') === $answerIsTrue,
                    'order' => $value === 'true' ? 0 : 1,
                ]);
            }
        }

        if ($type === QuestionType::Matching) {
            $order = 0;
            for ($i = 1; $i <= 5; $i++) {
                $left = $row['left_'.$i] ?? null;
                $right = $row['right_'.$i] ?? null;
                if ($left === null || $right === null) {
                    continue;
                }
                MatchingPair::create([
                    'question_id' => $question->id,
                    'left_text' => $left,
                    'right_text' => $right,
                    'order' => $order++,
                ]);
            }
        }
    }

    private function resolveType(string $raw): ?QuestionType
    {
        return match ($raw) {
            'pg', 'pilihan ganda', 'pilihan_ganda' => QuestionType::Pg,
            'pgk', 'pg kompleks', 'pg_kompleks', 'pilihan ganda kompleks' => QuestionType::Pgk,
            'boolean', 'benar/salah', 'benar-salah', 'b/s', 'b-s', 'bs', 'salah/benar' => QuestionType::Boolean,
            'matching', 'menjodohkan', 'jodoh', 'pasangkan' => QuestionType::Matching,
            default => null,
        };
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
            return in_array(strtolower(trim($value)), ['1', 'true', 'ya', 'yes', 'aktif', 'y'], true);
        }

        return (bool) $value;
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function writeCsv(array $rows, string $prefix): string
    {
        $tmp = tempnam(sys_get_temp_dir(), $prefix).'.csv';
        $handle = fopen($tmp, 'wb');
        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $tmp;
    }
}

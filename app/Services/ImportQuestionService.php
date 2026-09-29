<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\MatchingPair;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionTemplate;
use App\Models\User;
use App\Support\TabularReader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Impor soal massal dari file CSV atau XLSX.
 *
 * Alur admin:
 *   1. Admin mengunduh template dari menu "Template Soal"
 *   2. Admin mengisi file dengan data soal
 *   3. Admin mengunggah file kembali
 *   4. Service memvalidasi, memisahkan baris valid dan error, mengirimkannya
 *      ke Inertia sebagai preview
 *   5. Admin menekan "Impor" setelah yakin, service menulis ke database
 *
 * Template disediakan per tipe soal. Admin boleh memilih template yang sudah
 * disimpan di question_templates atau memakai template bawaan service ini.
 *
 * File CSV/TXT/XLSX dibaca lewat TabularReader (PhpSpreadsheet + deteksi
 * pemisah Excel locale Indonesia) — paket Excel yang terpasang tidak
 * menyediakan kelas SimpleExcel*.
 */
class ImportQuestionService
{
    public function __construct(private readonly AuditLogService $audit) {}

    /**
     * Download template bawaan (bukan yang tersimpan di question_templates).
     */
    public function downloadTemplate(QuestionType $type): string
    {
        $rows = $this->templateRows($type);
        $tmp = tempnam(sys_get_temp_dir(), 'cbt_tpl_').'.csv';

        $handle = fopen($tmp, 'wb');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return $tmp;
    }

    /**
     * Unduh file template yang tersimpan di question_templates bila ada,
     * bila tidak pakai template bawaan.
     */
    public function downloadStoredOrBuiltin(QuestionTemplate $template): string
    {
        if ($template->hasCustomFile() && file_exists(storage_path('app/public/'.$template->file_path))) {
            return storage_path('app/public/'.$template->file_path);
        }

        return $this->downloadTemplate($template->question_type);
    }

    /**
     * Parse dan validasi file unggahan.
     *
     * @return array{valid: array<int, array<string, mixed>>, errors: array<int, array<string, mixed>>}
     */
    public function parseFile(UploadedFile $file, QuestionType $type): array
    {
        $rows = TabularReader::rows($file);

        $valid = [];
        $errors = [];
        $rowIndex = 1;

        foreach ($rows as $row) {
            $rowIndex++;
            $normalized = $this->normalizeRow($row, $type);
            $validation = $this->validateRow($normalized, $type, $rowIndex);

            if ($validation === null) {
                $valid[] = $normalized;
            } else {
                $errors[] = [
                    'row' => $rowIndex,
                    'raw' => $normalized,
                    'errors' => $validation,
                ];
            }
        }

        return ['valid' => $valid, 'errors' => $errors];
    }

    /**
     * Tulis baris valid ke database.
     *
     * @param  array<int, array<string, mixed>>  $rows  hasil validasi dari parseFile
     * @return int jumlah soal yang berhasil diimpor
     */
    public function importRows(Exam $exam, array $rows, User $actor): int
    {
        $imported = 0;

        $maxOrder = (int) Question::query()
            ->where('exam_id', $exam->id)
            ->max('order');

        DB::transaction(function () use ($exam, $rows, $actor, &$imported, &$maxOrder) {
            foreach ($rows as $row) {
                $maxOrder++;
                $question = Question::create([
                    'exam_id' => $exam->id,
                    'type' => $row['type'],
                    'stimulus' => $row['stimulus'] ?? null,
                    'question_text' => $row['question_text'],
                    'media_url' => $row['media_url'] ?? null,
                    'order' => $maxOrder,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ]);

                $this->writeKeyAndOptions($question, $row);
                $imported++;
            }

            $this->audit->log(
                action: 'exam.questions.imported',
                subject: $exam,
                description: "{$imported} soal diimpor dari file.",
                meta: ['count' => $imported, 'type' => $rows[0]['type'] ?? null],
            );
        });

        return $imported;
    }

    // ------------------------------------------------------------------
    // Template
    // ------------------------------------------------------------------

    /**
     * @return array<int, array<int, string>>
     */
    private function templateRows(QuestionType $type): array
    {
        $header = match ($type) {
            QuestionType::Pg => [
                'nomor', 'stimulus', 'question_text', 'media_url',
                'option_A', 'option_B', 'option_C', 'option_D', 'option_E',
                'correct', 'is_active',
            ],
            QuestionType::Pgk => [
                'nomor', 'stimulus', 'question_text', 'media_url',
                'option_A', 'option_B', 'option_C', 'option_D', 'option_E',
                'correct', 'is_active',
            ],
            QuestionType::Boolean => [
                'nomor', 'stimulus', 'question_text', 'media_url',
                'correct', 'is_active',
            ],
            QuestionType::Matching => [
                'nomor', 'stimulus', 'question_text', 'media_url',
                'left_1', 'right_1', 'left_2', 'right_2',
                'left_3', 'right_3', 'left_4', 'right_4',
                'left_5', 'right_5', 'is_active',
            ],
        };

        $samples = match ($type) {
            QuestionType::Pg => [
                [1, '', 'Ibu kota Indonesia adalah...', '', 'Jakarta', 'Surabaya', 'Bandung', 'Medan', 'Makassar', 'A', 1],
                [2, '', '2 + 2 = ...', '', '3', '4', '5', '6', '', 'B', 1],
            ],
            QuestionType::Pgk => [
                [1, '', 'Manakah bilangan prima?', '', '2', '3', '4', '5', '9', 'A,B,D', 1],
            ],
            QuestionType::Boolean => [
                [1, '', 'Matahari terbit di timur.', '', 'Benar', 1],
                [2, '', 'Air mendidih pada 50°C.', '', 'Salah', 1],
            ],
            QuestionType::Matching => [
                [1, '', 'Pasangkan negara dengan ibu kotanya.', '',
                    'Indonesia', 'Jakarta',
                    'Jepang', 'Tokyo',
                    'Prancis', 'Paris',
                    'Italia', 'Roma',
                    '', '', 1],
            ],
        };

        return array_merge([$header], $samples);
    }

    // ------------------------------------------------------------------
    // Normalisasi + validasi
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row, QuestionType $type): array
    {
        $out = [
            'type' => $type->value,
            'nomor' => $this->coalesce($row, ['nomor', 'no', 'number']),
            'stimulus' => $this->coalesce($row, ['stimulus', 'wacana', 'bacaan']),
            'question_text' => $this->coalesce($row, ['question_text', 'pertanyaan', 'soal', 'text']),
            'media_url' => $this->coalesce($row, ['media_url', 'gambar', 'media']),
            'is_active' => $this->parseBool($this->coalesce($row, ['is_active', 'aktif'])),
        ];

        if ($type->usesOptions()) {
            foreach (['A', 'B', 'C', 'D', 'E'] as $label) {
                $key = 'option_'.$label;
                $out[$key] = $this->coalesce($row, [$key, strtolower($key), $label]);
            }

            $out['correct'] = strtoupper(trim((string) $this->coalesce($row, ['correct', 'kunci', 'jawaban'])));
        }

        if ($type === QuestionType::Boolean) {
            $out['correct'] = trim((string) $this->coalesce($row, ['correct', 'kunci', 'jawaban']));
        }

        if ($type === QuestionType::Matching) {
            for ($i = 1; $i <= 5; $i++) {
                $out['left_'.$i] = $this->coalesce($row, ['left_'.$i, 'kiri_'.$i, 'left'.$i]);
                $out['right_'.$i] = $this->coalesce($row, ['right_'.$i, 'kanan_'.$i, 'right'.$i]);
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<int, string>|null  null bila valid
     */
    private function validateRow(array $row, QuestionType $type, int $rowIndex): ?array
    {
        $errors = [];

        $questionText = trim((string) ($row['question_text'] ?? ''));

        if ($questionText === '') {
            $errors[] = 'Teks soal wajib diisi.';
        }

        if ($type->usesOptions()) {
            $correct = (string) ($row['correct'] ?? '');

            if ($correct === '') {
                $errors[] = 'Kunci jawaban wajib diisi.';
            }

            $provided = [];

            foreach (['A', 'B', 'C', 'D', 'E'] as $label) {
                $text = trim((string) ($row['option_'.$label] ?? ''));

                if ($text !== '') {
                    $provided[$label] = $text;
                }
            }

            if (count($provided) < 2) {
                $errors[] = 'Minimal dua opsi harus diisi.';
            }

            $this->validateCorrectLetters($correct, array_keys($provided), $type, $errors);
        }

        if ($type === QuestionType::Boolean) {
            $correct = strtolower((string) ($row['correct'] ?? ''));

            if (! in_array($correct, ['benar', 'salah', 'true', 'false', '1', '0'], true)) {
                $errors[] = 'Kunci benar/salah harus salah satu: Benar, Salah, true, false.';
            }
        }

        if ($type === QuestionType::Matching) {
            $pairs = 0;

            for ($i = 1; $i <= 5; $i++) {
                $left = trim((string) ($row['left_'.$i] ?? ''));
                $right = trim((string) ($row['right_'.$i] ?? ''));

                if ($left !== '' && $right !== '') {
                    $pairs++;
                } elseif ($left !== '' && $right === '') {
                    $errors[] = "Pasangan kiri {$i} tidak memiliki pasangan kanan.";
                } elseif ($left === '' && $right !== '') {
                    $errors[] = "Pasangan kanan {$i} tidak memiliki pasangan kiri.";
                }
            }

            if ($pairs < 2) {
                $errors[] = 'Minimal dua pasangan harus diisi.';
            }
        }

        return $errors === [] ? null : $errors;
    }

    /**
     * @param  array<int, string>  $availableLetters
     * @param  array<int, string>  &$errors
     */
    private function validateCorrectLetters(
        string $correct,
        array $availableLetters,
        QuestionType $type,
        array &$errors,
    ): void {
        if ($correct === '') {
            return;
        }

        $letters = array_map('trim', explode(',', strtoupper($correct)));
        $letters = array_values(array_filter($letters, fn ($l) => $l !== ''));

        if ($letters === []) {
            $errors[] = 'Kunci jawaban tidak terbaca.';

            return;
        }

        if ($type === QuestionType::Pg && count($letters) !== 1) {
            $errors[] = 'Pilihan ganda biasa harus memiliki tepat satu kunci.';
        }

        foreach ($letters as $letter) {
            if (! in_array($letter, $availableLetters, true)) {
                $errors[] = "Kunci '{$letter}' merujuk opsi yang tidak diisi.";
            }
        }
    }

    // ------------------------------------------------------------------
    // Penulisan ke database
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $row
     */
    private function writeKeyAndOptions(Question $question, array $row): void
    {
        $type = $question->type;

        if ($type->usesOptions()) {
            $correctLetters = array_map('trim', explode(',', strtoupper((string) ($row['correct'] ?? ''))));
            $correctLetters = array_values(array_filter($correctLetters, fn ($l) => $l !== ''));

            foreach (['A', 'B', 'C', 'D', 'E'] as $label) {
                $text = trim((string) ($row['option_'.$label] ?? ''));

                if ($text === '') {
                    continue;
                }

                Option::create([
                    'question_id' => $question->id,
                    'label' => $label,
                    'option_text' => $text,
                    'is_correct' => in_array($label, $correctLetters, true),
                    'order' => ord($label) - ord('A'),
                ]);
            }
        }

        if ($type === QuestionType::Boolean) {
            $rawCorrect = strtolower((string) ($row['correct'] ?? ''));
            $answerIsTrue = in_array($rawCorrect, ['benar', 'true', '1'], true);

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
                $left = trim((string) ($row['left_'.$i] ?? ''));
                $right = trim((string) ($row['right_'.$i] ?? ''));

                if ($left === '' || $right === '') {
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

    /**
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

    private function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $value = strtolower(trim($value));

            return in_array($value, ['1', 'true', 'ya', 'yes', 'aktif', 'ya'], true);
        }

        return (bool) $value;
    }
}

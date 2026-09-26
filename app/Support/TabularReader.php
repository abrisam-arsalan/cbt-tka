<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Membaca file CSV/TXT/XLSX/XLS menjadi daftar baris asosiatif (header -> nilai).
 * Header dinormalisasi ke huruf kecil + trim. Baris kosong dilewati.
 *
 * Dipakai oleh BulkImportService dan QuestionBankImportService. Sengaja memakai
 * CSV native + PhpSpreadsheet (bukan paket Excel Laravel) karena versi paket
 * yang terpasang tidak menyediakan kelas SimpleExcel*.
 */
class TabularReader
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public static function rows(UploadedFile $file): array
    {
        return self::rowsFromPath($file->getPathname(), strtolower($file->getClientOriginalExtension()));
    }

    /**
     * Varian path — dipakai juga untuk file hasil ekstrak ZIP.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function rowsFromPath(string $path, string $ext): array
    {
        $raw = in_array($ext, ['xlsx', 'xls'], true)
            ? self::spreadsheet($path)
            : self::csv($path);

        if ($raw === []) {
            return [];
        }

        $header = array_shift($raw);
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        $rows = [];

        foreach ($raw as $line) {
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
     * Deteksi pembatas kolom (koma / titik-koma / tab) dari baris pertama.
     *
     * Excel dengan locale Indonesia/Chines menyimpan CSV berpemisah ';' —
     * tanpa deteksi ini seluruh baris dianggap satu kolom dan ditolak.
     */
    public static function sniffDelimiter(string $path): string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return ',';
        }

        $line = '';

        while (($l = fgets($handle)) !== false) {
            if (trim($l) !== '') {
                $line = $l;
                break;
            }
        }

        fclose($handle);

        $line = (string) preg_replace('/^\xEF\xBB\xBF/', '', $line);

        $counts = [
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
            "\t" => substr_count($line, "\t"),
        ];
        arsort($counts);
        $best = (string) array_key_first($counts);

        return $counts[$best] > 0 ? $best : ',';
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private static function csv(string $path): array
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return [];
        }

        $delimiter = self::sniffDelimiter($path);
        $rows = [];
        $first = true;

        while (($data = fgetcsv($handle, 0, $delimiter, '"', '\\')) !== false) {
            if ($first && $data !== []) {
                $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $data[0]);
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
    private static function spreadsheet(string $path): array
    {
        return IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
    }
}

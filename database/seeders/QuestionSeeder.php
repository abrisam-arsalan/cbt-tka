<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\QuestionBankImportService;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;

/**
 * Membuat Bank Soal (batch) dummy lewat jalur impor yang sama dipakai admin,
 * sehingga template terpadu (campuran pg/pgk/boolean/matching + kelas) teruji.
 */
class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('username', 'admin')->first();
        $service = app(QuestionBankImportService::class);

        $this->importBatch($service, $admin, 'Matematika', AppModelsSchoolClass::query()->where('name', '7A')->value('id'), [
            ['pg', '7A', '', 'Berapakah hasil dari 2 + 2 × 3?', '', '4', '8', '12', '6', '10', 'B', '', '', '', '', '', '', '', '', '', '', 1],
            ['pgk', '7A', '', 'Manakah bilangan prima? (pilih semua)', '', '2', '3', '4', '5', '9', 'A,B,D', '', '', '', '', '', '', '', '', '', '', 1],
            ['boolean', '7A', '', 'Setiap bilangan prima adalah ganjil.', '', '', '', '', '', '', 'Salah', '', '', '', '', '', '', '', '', '', '', 1],
            ['matching', '7A', '', 'Pasangkan ibu kota dengan negaranya.', '', '', '', '', '', '', '', 'Indonesia', 'Jakarta', 'Jepang', 'Tokyo', 'Prancis', 'Paris', 'Italia', 'Roma', '', '', 1],
            ['pg', '7A', '', 'Berapakah akar dari 144?', '', '10', '11', '12', '13', '14', 'C', '', '', '', '', '', '', '', '', '', '', 1],
        ]);

        $this->importBatch($service, $admin, 'IPA Terpadu', AppModelsSchoolClass::query()->where('name', '8A')->value('id'), [
            ['pg', '8A', '', 'Planet terdekat dari Matahari adalah...', '', 'Venus', 'Merkurius', 'Mars', 'Bumi', 'Jupiter', 'B', '', '', '', '', '', '', '', '', '', '', 1],
            ['boolean', '8A', '', 'Air mendidih pada 100°C di tekanan normal.', '', '', '', '', '', '', 'Benar', '', '', '', '', '', '', '', '', '', '', 1],
            ['matching', '8A', '', 'Pasangkan gas dengan rumusnya.', '', '', '', '', '', '', '', 'Oksigen', 'O₂', 'Nitrogen', 'N₂', 'Karbon dioksida', 'CO₂', '', '', '', '', 1],
        ]);

        // Bank campuran tanpa kelas (bisa dipakai ujian kelas mana pun).
        $this->importBatch($service, $admin, 'Literasi Umum', null, [
            ['pg', '', '', 'Sinonim dari "pandai" adalah...', '', 'bodoh', 'cerdas', 'malas', 'lambat', 'kuat', 'B', '', '', '', '', '', '', '', '', '', '', 1],
            ['pg', '', '', 'Antonim dari "panas" adalah...', '', 'hangat', 'dingin', 'terik', 'sejuk', 'redup', 'B', '', '', '', '', '', '', '', '', '', '', 1],
        ]);
    }

    /**
     * @param  array<int, array<int, mixed>>  $dataRows
     */
    private function importBatch(QuestionBankImportService $service, User $admin, string $name, ?int $classId, array $dataRows): void
    {
        $path = tempnam(sys_get_temp_dir(), 'seed_bank_').'.csv';
        $handle = fopen($path, 'wb');
        fputcsv($handle, QuestionBankImportService::COLUMNS);
        foreach ($dataRows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $file = new UploadedFile($path, 'seed-bank.csv', 'text/csv', null, true);
        $result = $service->import($file, $name, $classId, $admin);

        @unlink($path);

        if ($result['errors'] !== []) {
            $this->command?->warn("Bank {$name}: ".count($result['errors']).' baris dilewati.');
        }
    }
}

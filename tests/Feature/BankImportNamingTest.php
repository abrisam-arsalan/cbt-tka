<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BankImportNamingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function csvFile(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'bank').'.csv';
        file_put_contents($path, implode("\n", [
            'jenis,pertanyaan,opsi_a,opsi_b,kunci,aktif',
            'pg,Ibu kota Indonesia adalah,Jakarta,Surabaya,A,1',
        ]));

        return new UploadedFile($path, 'soal.csv', 'text/csv', null, true);
    }

    public function test_impor_tanpa_mapel_ditolak(): void
    {
        $res = $this->actingAs($this->admin())->post(route('admin.bank.store'), [
            'tingkat' => '7',
            'file' => $this->csvFile(),
        ]);

        $res->assertSessionHasErrors('subject_id');
    }

    public function test_impor_tanpa_kelas_ditolak(): void
    {
        $res = $this->actingAs($this->admin())->post(route('admin.bank.store'), [
            'subject_id' => '3',
            'subject_code' => 'TIK',
            'file' => $this->csvFile(),
        ]);

        $res->assertSessionHasErrors('tingkat');
    }

    public function test_impor_kelas_invalid_ditolak(): void
    {
        $res = $this->actingAs($this->admin())->post(route('admin.bank.store'), [
            'subject_id' => '3',
            'subject_code' => 'TIK',
            'tingkat' => '10',
            'file' => $this->csvFile(),
        ]);

        $res->assertSessionHasErrors('tingkat');
    }

    public function test_nama_batch_otomatis_kode_kelas_tanggal(): void
    {
        $res = $this->actingAs($this->admin())->post(route('admin.bank.store'), [
            'subject_id' => '3',
            'subject_code' => 'TIK',
            'tingkat' => '7',
            'file' => $this->csvFile(),
        ]);

        $res->assertRedirect();
        $this->assertDatabaseHas('question_batches', [
            'name' => 'TIK_7_'.now()->format('dmY'),
        ]);
    }
}

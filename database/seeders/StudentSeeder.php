<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Database\Seeder;

/**
 * Contoh akun siswa.
 *
 * Aturan akun login (dicetak di kartu ujian):
 *  - username = NISN (murni, tanpa prefix);
 *  - password = PIN 6 digit ANGKA acak per siswa, dijamin beda dari username.
 */
class StudentSeeder extends Seeder
{
    public function run(): void
    {
        $pins = app(PinService::class);

        $students = [
            ['nisn' => '0010001001', 'name' => 'Andi Pratama', 'class' => 'Kelas 7'],
            ['nisn' => '0010001002', 'name' => 'Budi Santoso', 'class' => 'Kelas 7'],
            ['nisn' => '0010001003', 'name' => 'Citra Dewi', 'class' => 'Kelas 7'],
            ['nisn' => '0010001004', 'name' => 'Dian Permata', 'class' => 'Kelas 7'],
            ['nisn' => '0010001005', 'name' => 'Eko Prasetyo', 'class' => 'Kelas 7'],
        ];

        foreach ($students as $data) {
            $class = SchoolClass::query()->where('name', $data['class'])->first();

            $user = new User([
                'username' => $data['nisn'], // username = NISN
                'name' => $data['name'],
                'nisn' => $data['nisn'],
                'email' => null,
                'role' => 'siswa',
                'class_id' => $class?->id,
                'is_active' => true,
            ]);

            $pin = $pins->generate($user->username);
            $pins->apply($user, $pin);
            $user->save();
        }
    }
}

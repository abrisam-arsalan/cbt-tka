<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

/**
 * Contoh rombel SMP per jenjang (7/8/9). Jenjang = tingkat, rombel = kelas
 * paralel (7A, 7B, ...). Admin dapat menambah/menghapus lewat menu Kelas.
 */
class ClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['name' => '7A', 'grade' => '7'],
            ['name' => '7B', 'grade' => '7'],
            ['name' => '8A', 'grade' => '8'],
            ['name' => '8B', 'grade' => '8'],
            ['name' => '9A', 'grade' => '9'],
            ['name' => '9B', 'grade' => '9'],
        ];

        foreach ($classes as $class) {
            SchoolClass::create([
                'name' => $class['name'],
                'grade' => $class['grade'],
                'academic_year' => '2026/2027',
                'is_active' => true,
            ]);
        }
    }
}

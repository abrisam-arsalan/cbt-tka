<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

/**
 * Kelas kunci untuk jenjang SMP: hanya Kelas 7, 8, dan 9.
 */
class ClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            ['name' => 'Kelas 7', 'grade' => '7'],
            ['name' => 'Kelas 8', 'grade' => '8'],
            ['name' => 'Kelas 9', 'grade' => '9'],
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

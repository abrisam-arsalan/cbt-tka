<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use Illuminate\Database\Seeder;

class ClassSeeder extends Seeder
{
    public function run(): void
    {
        SchoolClass::create([
            'name' => 'X IPA 1',
            'grade' => '10',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);

        SchoolClass::create([
            'name' => 'XI IPA 2',
            'grade' => '11',
            'academic_year' => '2026/2027',
            'is_active' => true,
        ]);
    }
}
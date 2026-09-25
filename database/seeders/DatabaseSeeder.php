<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            ClassSeeder::class,
            StudentSeeder::class,
            QuestionSeeder::class, // buat Bank Soal lebih dulu
            ExamSeeder::class,     // ujian menyalin dari bank
            QuestionTemplateSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
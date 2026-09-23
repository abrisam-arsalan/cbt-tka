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
            ExamSeeder::class,
            QuestionSeeder::class,
            QuestionTemplateSeeder::class,
            SettingSeeder::class,
        ]);
    }
}
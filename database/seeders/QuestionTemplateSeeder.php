<?php

namespace Database\Seeders;

use App\Models\QuestionTemplate;
use Illuminate\Database\Seeder;

class QuestionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        QuestionTemplate::create([
            'name' => 'Template PG Bawaan',
            'description' => 'Template bawaan untuk soal pilihan ganda. Unduh, isi, lalu unggah kembali.',
            'question_type' => 'pg',
            'is_active' => true,
            'is_builtin' => true,
            'created_by' => 1,
            'columns' => json_encode([
                'nomor', 'stimulus', 'question_text', 'media_url',
                'option_A', 'option_B', 'option_C', 'option_D', 'option_E',
                'correct', 'is_active',
            ]),
            'sample_rows' => json_encode([
                [
                    'nomor' => 1,
                    'stimulus' => '',
                    'question_text' => 'Ibu kota Indonesia adalah...',
                    'media_url' => '',
                    'option_A' => 'Jakarta',
                    'option_B' => 'Surabaya',
                    'option_C' => 'Bandung',
                    'option_D' => 'Medan',
                    'option_E' => 'Makassar',
                    'correct' => 'A',
                    'is_active' => 1,
                ],
                [
                    'nomor' => 2,
                    'stimulus' => '',
                    'question_text' => '2 + 2 = ...',
                    'media_url' => '',
                    'option_A' => '3',
                    'option_B' => '4',
                    'option_C' => '5',
                    'option_D' => '6',
                    'option_E' => '',
                    'correct' => 'B',
                    'is_active' => 1,
                ],
            ]),
        ]);
    }
}
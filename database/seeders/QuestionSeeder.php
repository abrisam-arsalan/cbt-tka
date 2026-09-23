<?php

namespace Database\Seeders;

use App\Enums\QuestionType;
use App\Models\MatchingPair;
use App\Models\Option;
use App\Models\Question;
use Illuminate\Database\Seeder;

class QuestionSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // --- order 1: PG ---
        $q1 = Question::create([
            'exam_id' => 1, 'type' => QuestionType::Pg->value, 'order' => 1,
            'question_text' => 'Berapakah hasil dari 2 + 2 × 3?', 'is_active' => true,
        ]);
        foreach ([
            ['label' => 'A', 'option_text' => '4', 'is_correct' => false, 'order' => 0],
            ['label' => 'B', 'option_text' => '8', 'is_correct' => true, 'order' => 1],
            ['label' => 'C', 'option_text' => '12', 'is_correct' => false, 'order' => 2],
            ['label' => 'D', 'option_text' => '6', 'is_correct' => false, 'order' => 3],
            ['label' => 'E', 'option_text' => '10', 'is_correct' => false, 'order' => 4],
        ] as $o) {
            Option::create([...$o, 'question_id' => $q1->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        // --- order 2: PG ---
        $q2 = Question::create([
            'exam_id' => 1, 'type' => QuestionType::Pg->value, 'order' => 2,
            'question_text' => 'Manakah yang termasuk bilangan prima?', 'is_active' => true,
        ]);
        foreach ([
            ['label' => 'A', 'option_text' => '9', 'is_correct' => false, 'order' => 0],
            ['label' => 'B', 'option_text' => '15', 'is_correct' => false, 'order' => 1],
            ['label' => 'C', 'option_text' => '17', 'is_correct' => true, 'order' => 2],
            ['label' => 'D', 'option_text' => '21', 'is_correct' => false, 'order' => 3],
            ['label' => 'E', 'option_text' => '27', 'is_correct' => false, 'order' => 4],
        ] as $o) {
            Option::create([...$o, 'question_id' => $q2->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        // --- order 3: PGK ---
        $q3 = Question::create([
            'exam_id' => 1, 'type' => QuestionType::Pgk->value, 'order' => 3,
            'question_text' => 'Manakah pernyataan yang benar tentang segitiga? (pilih semua)',
            'is_active' => true,
        ]);
        foreach ([
            ['label' => 'A', 'option_text' => 'Jumlah sudut 180°', 'is_correct' => true, 'order' => 0],
            ['label' => 'B', 'option_text' => 'Segitiga sama sisi selalu memiliki sudut 60°', 'is_correct' => true, 'order' => 1],
            ['label' => 'C', 'option_text' => 'Segitiga bisa memiliki dua sudut tumpul', 'is_correct' => false, 'order' => 2],
            ['label' => 'D', 'option_text' => 'Phytagoras berlaku untuk segitiga siku-siku', 'is_correct' => true, 'order' => 3],
        ] as $o) {
            Option::create([...$o, 'question_id' => $q3->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        // --- order 4: PGK ---
        $q4 = Question::create([
            'exam_id' => 1, 'type' => QuestionType::Pgk->value, 'order' => 4,
            'question_text' => 'Manakah dari berikut ini yang termasuk bilangan genap?',
            'is_active' => true,
        ]);
        foreach ([
            ['label' => 'A', 'option_text' => '2', 'is_correct' => true, 'order' => 0],
            ['label' => 'B', 'option_text' => '4', 'is_correct' => true, 'order' => 1],
            ['label' => 'C', 'option_text' => '7', 'is_correct' => false, 'order' => 2],
            ['label' => 'D', 'option_text' => '8', 'is_correct' => true, 'order' => 3],
            ['label' => 'E', 'option_text' => '11', 'is_correct' => false, 'order' => 4],
        ] as $o) {
            Option::create([...$o, 'question_id' => $q4->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        // --- order 5: Boolean (Salah) ---
        $q5 = Question::create([
            'exam_id' => 1, 'type' => QuestionType::Boolean->value, 'order' => 5,
            'question_text' => 'Setiap bilangan prima adalah ganjil.', 'is_active' => true,
        ]);
        foreach ([
            ['label' => 'true', 'option_text' => 'Benar', 'is_correct' => false, 'order' => 0],
            ['label' => 'false', 'option_text' => 'Salah', 'is_correct' => true, 'order' => 1],
        ] as $o) {
            Option::create([...$o, 'question_id' => $q5->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        // --- order 6: Boolean (Benar) ---
        $q6 = Question::create([
            'exam_id' => 1, 'type' => QuestionType::Boolean->value, 'order' => 6,
            'question_text' => 'Air terdiri dari hidrogen dan oksigen (H₂O).', 'is_active' => true,
        ]);
        foreach ([
            ['label' => 'true', 'option_text' => 'Benar', 'is_correct' => true, 'order' => 0],
            ['label' => 'false', 'option_text' => 'Salah', 'is_correct' => false, 'order' => 1],
        ] as $o) {
            Option::create([...$o, 'question_id' => $q6->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        // --- order 7: Matching ---
        $q7 = Question::create([
            'exam_id' => 1, 'type' => QuestionType::Matching->value, 'order' => 7,
            'question_text' => 'Pasangkan ibu kota dengan negaranya.', 'is_active' => true,
        ]);
        foreach ([
            ['left_text' => 'Indonesia', 'right_text' => 'Jakarta', 'order' => 0],
            ['left_text' => 'Jepang', 'right_text' => 'Tokyo', 'order' => 1],
            ['left_text' => 'Mesir', 'right_text' => 'Kairo', 'order' => 2],
            ['left_text' => 'Brasil', 'right_text' => 'Brasilia', 'order' => 3],
        ] as $p) {
            MatchingPair::create([...$p, 'question_id' => $q7->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        // --- order 8: PG ---
        $q8 = Question::create([
            'exam_id' => 1, 'type' => QuestionType::Pg->value, 'order' => 8,
            'question_text' => 'Berapakah √144?', 'is_active' => true,
        ]);
        foreach ([
            ['label' => 'A', 'option_text' => '10', 'is_correct' => false, 'order' => 0],
            ['label' => 'B', 'option_text' => '11', 'is_correct' => false, 'order' => 1],
            ['label' => 'C', 'option_text' => '12', 'is_correct' => true, 'order' => 2],
            ['label' => 'D', 'option_text' => '13', 'is_correct' => false, 'order' => 3],
            ['label' => 'E', 'option_text' => '14', 'is_correct' => false, 'order' => 4],
        ] as $o) {
            Option::create([...$o, 'question_id' => $q8->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        // --- order 9: PG ---
        $q9 = Question::create([
            'exam_id' => 1, 'type' => QuestionType::Pg->value, 'order' => 9,
            'question_text' => 'Rumus kimia air adalah...', 'is_active' => true,
        ]);
        foreach ([
            ['label' => 'A', 'option_text' => 'CO₂', 'is_correct' => false, 'order' => 0],
            ['label' => 'B', 'option_text' => 'NaCl', 'is_correct' => false, 'order' => 1],
            ['label' => 'C', 'option_text' => 'H₂O', 'is_correct' => true, 'order' => 2],
            ['label' => 'D', 'option_text' => 'O₂', 'is_correct' => false, 'order' => 3],
            ['label' => 'E', 'option_text' => 'H₂SO₄', 'is_correct' => false, 'order' => 4],
        ] as $o) {
            Option::create([...$o, 'question_id' => $q9->id, 'created_at' => $now, 'updated_at' => $now]);
        }

        // --- order 10: Matching ---
        $q10 = Question::create([
            'exam_id' => 1, 'type' => QuestionType::Matching->value, 'order' => 10,
            'question_text' => 'Pasangkan penemu dengan temuannya.', 'is_active' => true,
        ]);
        foreach ([
            ['left_text' => 'Thomas Edison', 'right_text' => 'Bola lampu', 'order' => 0],
            ['left_text' => 'Alexander Graham Bell', 'right_text' => 'Telepon', 'order' => 1],
            ['left_text' => 'Wright Bersaudara', 'right_text' => 'Pesawat terbang', 'order' => 2],
        ] as $p) {
            MatchingPair::create([...$p, 'question_id' => $q10->id, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
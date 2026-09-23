<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Services\TokenGenerator;
use Illuminate\Database\Seeder;

class ExamSeeder extends Seeder
{
    public function run(): void
    {
        $exam = Exam::create([
            'title' => 'Ujian Matematika - Semester Ganjil 2026',
            'description' => 'Ujian akhir semester ganjil mata pelajaran Matematika Wajib. Kerjakan dengan teliti.',
            'duration_minutes' => 60,
            'status' => 'active',
            'anti_cheat_enabled' => true,
            'anti_cheat_action' => 'warning_only',
            'anti_cheat_max_warnings' => 3,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'offline_grace_minutes' => 5,
            'created_by' => 1,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHours(2),
        ]);

        $generator = TokenGenerator::fromConfig();

        for ($userId = 2; $userId <= 6; $userId++) {
            $raw = $generator->generateRaw();
            $hash = hash('sha256', $raw);

            ExamParticipant::create([
                'exam_id' => $exam->id,
                'user_id' => $userId,
                'token_hash' => $hash,
                'token_cipher' => $raw,
                'token_generated_at' => now(),
                'is_active' => true,
            ]);
        }
    }
}
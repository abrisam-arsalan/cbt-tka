<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamParticipant;
use App\Models\QuestionBatch;
use App\Models\User;
use App\Services\QuestionCopyService;
use App\Services\TokenGenerator;
use Illuminate\Database\Seeder;

/**
 * Membuat ujian dummy: tiap ujian terikat satu kelas, menyalin soal dari bank,
 * lalu peserta diambil dari siswa kelas tersebut.
 */
class ExamSeeder extends Seeder
{
    public function run(): void
    {
        $copy = app(QuestionCopyService::class);
        $generator = TokenGenerator::fromConfig();

        $this->makeExam($copy, $generator, [
            'title' => 'Ujian Matematika - Semester Ganjil 2026',
            'description' => 'Ujian akhir semester ganjil Matematika Wajib. Kerjakan dengan teliti.',
            'class_id' => 1,
            'bank' => 'Matematika',
            'duration_minutes' => 60,
            'anti_cheat' => true,
        ]);

        $this->makeExam($copy, $generator, [
            'title' => 'Penilaian Harian IPA Terpadu',
            'description' => 'Soal IPA Terpadu kelas XI. Perhatikan petunjuk setiap nomor.',
            'class_id' => 2,
            'bank' => 'IPA Terpadu',
            'duration_minutes' => 45,
            'anti_cheat' => false,
        ]);

        $this->makeExam($copy, $generator, [
            'title' => 'Try Out Literasi (Semua Kelas)',
            'description' => 'Latihan literasi umum, terbuka untuk semua kelas.',
            'class_id' => null,
            'bank' => 'Literasi Umum',
            'duration_minutes' => 30,
            'anti_cheat' => false,
        ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function makeExam(QuestionCopyService $copy, TokenGenerator $generator, array $config): void
    {
        $exam = Exam::create([
            'title' => $config['title'],
            'description' => $config['description'],
            'class_id' => $config['class_id'],
            'duration_minutes' => $config['duration_minutes'],
            'status' => 'active',
            'anti_cheat_enabled' => $config['anti_cheat'],
            'anti_cheat_action' => 'warning_only',
            'anti_cheat_max_warnings' => 3,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'offline_grace_minutes' => 5,
            'created_by' => 1,
            'start_at' => now()->subHour(),
            'end_at' => now()->addHours(3),
        ]);

        // Salin soal dari bank terpilih.
        $batch = QuestionBatch::query()->where('name', 'like', $config['bank'].'%')->orderByDesc('id')->first();
        if ($batch !== null) {
            $copy->copyBatchesToExam([$batch->id], $exam);
        }

        // Peserta: siswa pada kelas ujian (atau semua siswa bila tanpa kelas).
        $students = User::query()
            ->siswa()
            ->active()
            ->when($config['class_id'], fn ($q) => $q->where('class_id', $config['class_id']))
            ->get();

        foreach ($students as $student) {
            $raw = $generator->generateRaw();
            ExamParticipant::create([
                'exam_id' => $exam->id,
                'user_id' => $student->id,
                'token_hash' => hash('sha256', $raw),
                'token_cipher' => $raw,
                'token_generated_at' => now(),
                'is_active' => true,
            ]);
        }
    }
}

<?php

namespace App\Services\Scoring;

/**
 * Hasil penilaian satu attempt.
 *
 * @param  array<int, array<string, mixed>>  $perQuestion  rincian per soal untuk halaman hasil
 */
final class ScoreResult
{
    public function __construct(
        public readonly int $totalQuestions,
        public readonly int $correctCount,
        public readonly int $wrongCount,
        public readonly int $unansweredCount,
        public readonly float $score,
        public readonly array $perQuestion = [],
    ) {}

    public function toArray(): array
    {
        return [
            'total_questions' => $this->totalQuestions,
            'correct_count' => $this->correctCount,
            'wrong_count' => $this->wrongCount,
            'unanswered_count' => $this->unansweredCount,
            'score' => $this->score,
        ];
    }
}

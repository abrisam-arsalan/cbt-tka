<?php

namespace Tests\Unit;

use App\Enums\QuestionType;
use App\Models\Answer;
use App\Models\MatchingPair;
use App\Models\Option;
use App\Models\Question;
use App\Services\ScoringService;
use Tests\TestCase;

class ScoringServiceTest extends TestCase
{
    private ScoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ScoringService::class);
    }

    private function makeQuestion(QuestionType $type, array $options = [], array $pairs = []): Question
    {
        $question = new Question(['type' => $type]);

        if ($options !== []) {
            $collection = collect();
            foreach ($options as $index => $option) {
                $o = new Option();
                $o->forceFill(array_merge(['id' => $index + 1, 'order' => $index], $option));
                $collection->push($o);
            }
            $question->setRelation('options', $collection);
        }

        if ($pairs !== []) {
            $collection = collect();
            foreach ($pairs as $index => $pair) {
                $p = new MatchingPair();
                $p->forceFill(array_merge(['id' => $index + 1], $pair));
                $collection->push($p);
            }
            $question->setRelation('matchingPairs', $collection);
        }

        return $question;
    }

    private function makeAnswer(array $payload): Answer
    {
        $answer = new Answer();
        $answer->forceFill(['answer_payload' => $payload]);

        return $answer;
    }

    public function test_pg_scores_correct_when_option_matches_key(): void
    {
        $question = $this->makeQuestion(QuestionType::Pg, [
            ['label' => 'A', 'is_correct' => false],
            ['label' => 'B', 'is_correct' => true],
            ['label' => 'C', 'is_correct' => false],
        ]);

        $this->assertTrue($this->service->judge($question, $this->makeAnswer(['option_id' => 2])));
        $this->assertFalse($this->service->judge($question, $this->makeAnswer(['option_id' => 1])));
        $this->assertFalse($this->service->judge($question, $this->makeAnswer(['option_id' => null])));
    }

    public function test_pgk_requires_exact_set_match(): void
    {
        $question = $this->makeQuestion(QuestionType::Pgk, [
            ['label' => 'A', 'is_correct' => true],
            ['label' => 'B', 'is_correct' => true],
            ['label' => 'C', 'is_correct' => false],
            ['label' => 'D', 'is_correct' => true],
        ]);

        // Himpunan sama persis (urutan bebas) -> benar.
        $this->assertTrue($this->service->judge($question, $this->makeAnswer(['option_ids' => [4, 1, 2]])));

        // Kurang satu -> salah.
        $this->assertFalse($this->service->judge($question, $this->makeAnswer(['option_ids' => [1, 2]])));

        // Lebih satu (ikut yang salah) -> salah.
        $this->assertFalse($this->service->judge($question, $this->makeAnswer(['option_ids' => [1, 2, 3, 4]])));

        // Kosong -> salah.
        $this->assertFalse($this->service->judge($question, $this->makeAnswer(['option_ids' => []])));
    }

    public function test_boolean_scores_against_key(): void
    {
        $question = $this->makeQuestion(QuestionType::Boolean, [
            ['label' => 'true', 'is_correct' => false],
            ['label' => 'false', 'is_correct' => true],
        ]);

        $this->assertTrue($this->service->judge($question, $this->makeAnswer(['value' => false])));
        $this->assertFalse($this->service->judge($question, $this->makeAnswer(['value' => true])));
        $this->assertFalse($this->service->judge($question, $this->makeAnswer(['value' => null])));
    }

    public function test_matching_is_all_or_nothing(): void
    {
        $question = $this->makeQuestion(QuestionType::Matching, pairs: [
            ['left_text' => 'Indonesia', 'right_text' => 'Jakarta'],
            ['left_text' => 'Jepang', 'right_text' => 'Tokyo'],
            ['left_text' => 'Mesir', 'right_text' => 'Kairo'],
        ]);

        // Semua pasangan tepat (id kiri dipetakan ke dirinya sendiri).
        $this->assertTrue($this->service->judge($question, $this->makeAnswer([
            'mapping' => ['1' => 1, '2' => 2, '3' => 3],
        ])));

        // Satu pasangan tertukar -> salah (bukan nilai parsial).
        $this->assertFalse($this->service->judge($question, $this->makeAnswer([
            'mapping' => ['1' => 2, '2' => 1, '3' => 3],
        ])));

        // Pasangan tidak lengkap -> salah.
        $this->assertFalse($this->service->judge($question, $this->makeAnswer([
            'mapping' => ['1' => 1, '2' => 2],
        ])));

        // Tidak dijawab -> salah.
        $this->assertFalse($this->service->judge($question, $this->makeAnswer(['mapping' => []])));
    }
}

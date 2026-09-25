<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\MatchingPair;
use App\Models\Option;
use App\Models\Question;
use App\Models\QuestionBatch;
use Illuminate\Support\Facades\DB;

/**
 * Menyalin soal BANK (exam_id null) menjadi soal MILIK UJIAN.
 *
 * Ini inti keputusan "salin saat ujian dibuat": soal ujian adalah salinan
 * mandiri (exam_id terisi, batch_id null), sehingga mengedit bank setelahnya
 * tidak menyentuh ujian yang sudah memakai soal tersebut.
 */
class QuestionCopyService
{
    /**
     * @param  array<int, int>  $batchIds
     * @return int jumlah soal yang disalin
     */
    public function copyBatchesToExam(array $batchIds, Exam $exam): int
    {
        if ($batchIds === []) {
            return 0;
        }

        $copied = 0;

        DB::transaction(function () use ($batchIds, $exam, &$copied) {
            $order = (int) $exam->questions()->max('order');

            $questions = Question::query()
                ->with(['options', 'matchingPairs'])
                ->whereIn('batch_id', $batchIds)
                ->whereNull('exam_id')
                ->where('is_active', true)
                ->orderBy('batch_id')
                ->orderBy('order')
                ->get();

            foreach ($questions as $question) {
                $order++;
                $this->copyQuestionToExam($question, $exam, $order);
                $copied++;
            }
        });

        return $copied;
    }

    public function copyQuestionToExam(Question $source, Exam $exam, int $order): Question
    {
        $copy = Question::create([
            'exam_id' => $exam->id,
            'batch_id' => null,
            'class_id' => $source->class_id ?? $exam->class_id,
            'type' => $source->type->value,
            'stimulus' => $source->stimulus,
            'question_text' => $source->question_text,
            'media_url' => $source->media_url,
            'order' => $order,
            'is_active' => true,
        ]);

        foreach ($source->options as $option) {
            Option::create([
                'question_id' => $copy->id,
                'label' => $option->label,
                'option_text' => $option->option_text,
                'media_url' => $option->media_url,
                'is_correct' => $option->is_correct,
                'order' => $option->order,
            ]);
        }

        foreach ($source->matchingPairs as $pair) {
            MatchingPair::create([
                'question_id' => $copy->id,
                'left_text' => $pair->left_text,
                'right_text' => $pair->right_text,
                'left_media_url' => $pair->left_media_url,
                'right_media_url' => $pair->right_media_url,
                'order' => $pair->order,
            ]);
        }

        return $copy;
    }
}

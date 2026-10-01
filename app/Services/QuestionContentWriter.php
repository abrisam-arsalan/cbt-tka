<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Option;
use App\Models\Question;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

/**
 * Menyimpan opsi & pasangan menjodohkan TANPA mengganti ID baris yang masih
 * dipakai.
 *
 * Latar belakang: dulu simpan-soal dilakukan dengan menghapus SEMUA opsi lalu
 * membuat ulang. ID opsi berganti setiap kali soal diedit — padahal jawaban
 * siswa menyimpan option_id. Akibatnya jawaban siswa yang benar bisa dinilai
 * salah hanya karena admin mengubah teks soal. Writer ini memperbarui baris
 * yang cocok (pertama lewat ID kiriman form, cadangannya lewat label/teks),
 * membuat baris baru hanya untuk opsi baru, dan menghapus hanya baris yang
 * benar-benar hilang dari daftar.
 */
class QuestionContentWriter
{
    /**
     * @param  array<int, array<string, mixed>>  $incoming
     */
    public function syncOptions(Question $question, array $incoming, QuestionType $type): void
    {
        $existing = $question->options()->get();
        $claimed = [];
        $position = 0;

        foreach ($incoming as $option) {
            $text = trim((string) ($option['option_text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $label = trim((string) ($option['label'] ?? ''));

            if ($label === '') {
                $label = chr(ord('A') + $position);
            }

            $payload = [
                'label' => $label,
                'option_text' => $text,
                'is_correct' => (bool) ($option['is_correct'] ?? false),
                'order' => $position,
            ];

            // Boolean: urutan baku true=0 false=1 (konsisten dengan impor).
            if ($type === QuestionType::Boolean) {
                $payload['order'] = $label === 'true' ? 0 : 1;
            }

            // media_url hanya ditimpa bila form mengirimkannya; kalau tidak,
            // gambar opsi lama tetap utuh.
            if (array_key_exists('media_url', $option)) {
                $payload['media_url'] = $option['media_url'];
            }

            $target = $this->pickTarget($existing, $claimed, (int) ($option['id'] ?? 0), fn (Option $o) => $o->label === $label);

            if ($target !== null) {
                $target->update($payload);
                $claimed[$target->id] = true;
            } else {
                $question->options()->create($payload);
            }

            $position++;
        }

        $existing->reject(fn (Option $o) => isset($claimed[$o->id]))->each->delete();
    }

    /**
     * @param  array<int, array<string, mixed>>  $incoming
     */
    public function syncPairs(Question $question, array $incoming): void
    {
        $existing = $question->matchingPairs()->get();
        $claimed = [];
        $position = 0;

        foreach ($incoming as $pair) {
            $left = trim((string) ($pair['left_text'] ?? ''));
            $right = trim((string) ($pair['right_text'] ?? ''));

            if ($left === '' || $right === '') {
                continue;
            }

            $payload = [
                'left_text' => $left,
                'right_text' => $right,
                'order' => $position,
            ];

            foreach (['left_media_url', 'right_media_url'] as $mediaKey) {
                if (array_key_exists($mediaKey, $pair)) {
                    $payload[$mediaKey] = $pair[$mediaKey];
                }
            }

            $target = $this->pickTarget($existing, $claimed, (int) ($pair['id'] ?? 0), fn (Model $p) => (string) $p->left_text === $left);

            if ($target !== null) {
                $target->update($payload);
                $claimed[$target->id] = true;
            } else {
                $question->matchingPairs()->create($payload);
            }

            $position++;
        }

        $existing->reject(fn (Model $p) => isset($claimed[$p->id]))->each->delete();
    }

    /**
     * Baris lama yang berhak dipakai lagi: cocok ID dulu, baru cocok label/teks.
     *
     * @param  Collection<int, Model>  $existing
     * @param  array<int, true>  $claimed
     */
    private function pickTarget(Collection $existing, array $claimed, int $id, callable $fallbackMatch): ?Model
    {
        if ($id > 0) {
            $row = $existing->firstWhere('id', $id);

            if ($row !== null && ! isset($claimed[$row->id])) {
                return $row;
            }
        }

        return $existing->first(fn (Model $row) => ! isset($claimed[$row->id]) && $fallbackMatch($row));
    }
}

<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'exam_id', 'type', 'stimulus', 'question_text',
    'media_url', 'order', 'is_active',
])]
class Question extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => QuestionType::class,
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(Option::class)->orderBy('order')->orderBy('id');
    }

    public function matchingPairs(): HasMany
    {
        return $this->hasMany(MatchingPair::class)->orderBy('order')->orderBy('id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }

    public function scopeOfType(Builder $query, QuestionType|string $type): Builder
    {
        return $query->where('type', $type instanceof QuestionType ? $type->value : $type);
    }

    /**
     * ID opsi yang menjadi kunci jawaban.
     *
     * pg  -> satu elemen.
     * pgk -> seluruh himpunan opsi benar (dinilai sebagai satu paket).
     *
     * @return array<int, int>
     */
    public function correctOptionIds(): array
    {
        return $this->options
            ->filter(fn (Option $option) => $option->is_correct)
            ->map(fn (Option $option) => (int) $option->id)
            ->values()
            ->all();
    }

    /**
     * ID pasangan untuk soal menjodohkan.
     *
     * @return array<int, int>
     */
    public function pairIds(): array
    {
        return $this->matchingPairs
            ->map(fn (MatchingPair $pair) => (int) $pair->id)
            ->all();
    }

    /**
     * Kunci jawaban untuk tipe boolean.
     *
     * Soal benar/salah tidak punya kolom kunci tersendiri. Kuncinya disimpan
     * di tabel options sebagai dua baris berlabel "true" dan "false", dengan
     * is_correct menandai yang benar. Cara ini membuat penyimpanan kunci
     * seragam untuk pg, pgk, dan boolean, serta membuat shuffle_options
     * tetap berfungsi pada soal benar/salah.
     */
    public function booleanKey(): ?bool
    {
        if ($this->type !== QuestionType::Boolean) {
            return null;
        }

        $correct = $this->options->first(fn (Option $option) => $option->is_correct);

        if ($correct === null) {
            return null;
        }

        return filter_var((string) $correct->label, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? filter_var($correct->option_text, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    /**
     * Apakah soal ini punya kunci yang cukup untuk dinilai.
     *
     * Soal tanpa kunci akan dihitung sebagai salah oleh ScoringService —
     * lebih aman daripada memberi nilai cuma-cuma akibat data yang rusak.
     */
    public function hasValidKey(): bool
    {
        return match ($this->type) {
            QuestionType::Pg => count($this->correctOptionIds()) === 1,
            QuestionType::Pgk => count($this->correctOptionIds()) >= 1,
            // Disimpan sebagai dua opsi (true/false) dengan satu yang benar.
            QuestionType::Boolean => count($this->correctOptionIds()) === 1,
            QuestionType::Matching => count($this->pairIds()) >= 1,
        };
    }

    /**
     * Jumlah opsi/pasangan yang harus ditampilkan siswa.
     */
    public function itemCount(): int
    {
        return $this->type->usesOptions()
            ? $this->options->count()
            : $this->matchingPairs->count();
    }
}

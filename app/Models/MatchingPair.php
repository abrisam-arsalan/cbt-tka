<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris = satu pasangan benar pada soal menjodohkan.
 *
 * left_text harus dipasangkan dengan right_text dari baris yang sama,
 * sehingga kunci jawaban tersirat dari identitas baris itu sendiri.
 */
#[Fillable(['question_id', 'left_text', 'right_text', 'left_media_url', 'right_media_url', 'order'])]
class MatchingPair extends Model
{
    use HasFactory;

    protected $table = 'matching_pairs';

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order')->orderBy('id');
    }
}

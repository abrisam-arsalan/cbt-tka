<?php

namespace App\Models;

use App\Enums\QuestionType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'name', 'description', 'question_type', 'file_path',
    'columns', 'sample_rows', 'is_active', 'is_builtin', 'created_by',
])]
class QuestionTemplate extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'question_type' => QuestionType::class,
            'columns' => 'array',
            'sample_rows' => 'array',
            'is_active' => 'boolean',
            'is_builtin' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType(Builder $query, QuestionType|string $type): Builder
    {
        return $query->where('question_type', $type instanceof QuestionType ? $type->value : $type);
    }

    public function hasCustomFile(): bool
    {
        return is_string($this->file_path) && $this->file_path !== '';
    }
}

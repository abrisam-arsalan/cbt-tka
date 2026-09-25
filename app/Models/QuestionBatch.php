<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu "Bank Soal" = satu kelompok soal hasil impor (atau kumpulan yang
 * dikelola admin). Saat ujian dibuat, soal dari batch DISALIN ke ujian,
 * sehingga mengubah batch tidak memengaruhi ujian yang sudah memakai isinya.
 */
#[Fillable(['name', 'class_id', 'description', 'created_by'])]
class QuestionBatch extends Model
{
    use HasFactory;

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class, 'batch_id');
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}

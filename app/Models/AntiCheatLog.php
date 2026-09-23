<?php

namespace App\Models;

use App\Enums\AntiCheatEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only: tidak ada updated_at karena baris log tidak boleh berubah.
 */
#[Fillable(['attempt_id', 'type', 'message', 'meta', 'created_at'])]
class AntiCheatLog extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => AntiCheatEventType::class,
            'meta' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function scopeOfType(Builder $query, AntiCheatEventType|string $type): Builder
    {
        return $query->where('type', $type instanceof AntiCheatEventType ? $type->value : $type);
    }

    public function scopeRecent(Builder $query): Builder
    {
        return $query->orderByDesc('created_at')->orderByDesc('id');
    }
}

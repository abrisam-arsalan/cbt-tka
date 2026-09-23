<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penyimpanan presence bila driver Redis tidak dipakai.
 *
 * Satu baris per attempt (unique index), diperbarui dengan upsert.
 * Lihat komentar pada migration untuk alasan desain ini.
 */
#[Fillable([
    'exam_id', 'attempt_id', 'user_id', 'status',
    'last_seen_at', 'last_client_seq', 'outbox_pending', 'ip_address', 'meta',
])]
class PresenceHeartbeat extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_client_seq' => 'integer',
            'outbox_pending' => 'integer',
            'meta' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Baris presence yang masih dianggap online.
     */
    public function scopeOnline(Builder $query): Builder
    {
        $cutoff = now()->subSeconds((int) config('cbt.presence.online_seconds', 45));

        return $query->where('last_seen_at', '>=', $cutoff);
    }

    public function scopeForExam(Builder $query, int $examId): Builder
    {
        return $query->where('exam_id', $examId);
    }

    public function isOnline(): bool
    {
        if ($this->last_seen_at === null) {
            return false;
        }

        $cutoff = now()->subSeconds((int) config('cbt.presence.online_seconds', 45));

        return $this->last_seen_at->greaterThanOrEqualTo($cutoff);
    }
}

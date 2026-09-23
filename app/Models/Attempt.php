<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Enums\SubmitReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

#[Fillable([
    'exam_id', 'user_id', 'exam_participant_id', 'status',
    'started_at', 'deadline_at', 'expires_at', 'expired_at', 'locked_at', 'submitted_at',
    'lock_reason', 'submit_reason', 'extended_minutes', 'shuffle_seed',
    'warnings_count', 'anti_cheat_enforced',
    'total_questions', 'correct_count', 'wrong_count', 'unanswered_count', 'score',
    'started_ip', 'submitted_ip', 'user_agent',
])]
class Attempt extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'submit_reason' => SubmitReason::class,
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'expires_at' => 'datetime',
            'expired_at' => 'datetime',
            'locked_at' => 'datetime',
            'submitted_at' => 'datetime',
            'extended_minutes' => 'integer',
            'shuffle_seed' => 'integer',
            'warnings_count' => 'integer',
            'anti_cheat_enforced' => 'boolean',
            'total_questions' => 'integer',
            'correct_count' => 'integer',
            'wrong_count' => 'integer',
            'unanswered_count' => 'integer',
            'score' => 'decimal:2',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(ExamParticipant::class, 'exam_participant_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function antiCheatLogs(): HasMany
    {
        return $this->hasMany(AntiCheatLog::class)->orderByDesc('created_at');
    }

    public function presence(): HasOne
    {
        return $this->hasOne(PresenceHeartbeat::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AttemptStatus::InProgress->value,
            AttemptStatus::Locked->value,
        ]);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', AttemptStatus::Submitted->value);
    }

    /**
     * Attempt yang melewati deadline tetapi belum disubmit.
     *
     * Inilah kumpulan yang disapu scheduler setiap menit.
     */
    public function scopeOverdueForAutoSubmit(Builder $query, ?Carbon $now = null): Builder
    {
        $now ??= now();

        return $query
            ->whereIn('status', [
                AttemptStatus::InProgress->value,
                AttemptStatus::Locked->value,
                AttemptStatus::Expired->value,
            ])
            ->whereNotNull('deadline_at')
            ->where('deadline_at', '<=', $now);
    }

    /**
     * Sisa detik pengerjaan. Nol bila deadline sudah lewat atau belum dimulai.
     *
     * Dihitung dari selisih timestamp Unix, bukan diffInSeconds(), supaya
     * hasilnya tidak bergantung pada perilaku tanda (signed/unsigned) yang
     * berbeda antar versi Carbon.
     */
    public function remainingSeconds(?Carbon $now = null): int
    {
        if ($this->deadline_at === null || $this->submitted_at !== null) {
            return 0;
        }

        $now ??= now();

        return max(0, $this->deadline_at->getTimestamp() - $now->getTimestamp());
    }

    public function isPastDeadline(?Carbon $now = null): bool
    {
        $now ??= now();

        return $this->deadline_at !== null && $now->greaterThanOrEqualTo($this->deadline_at);
    }

    /**
     * Apakah grace period offline sudah benar-benar habis.
     *
     * Setelah titik ini, AnswerSyncService menolak semua jawaban baru.
     */
    public function isPastGracePeriod(?Carbon $now = null): bool
    {
        $now ??= now();

        $cutoff = $this->expires_at ?? $this->deadline_at;

        return $cutoff !== null && $now->greaterThan($cutoff);
    }

    public function isSubmitted(): bool
    {
        return $this->status === AttemptStatus::Submitted;
    }

    public function isLocked(): bool
    {
        return $this->status === AttemptStatus::Locked;
    }

    /**
     * Apakah siswa masih boleh mengirim jawaban.
     *
     * Berbeda dengan "masih punya waktu": setelah deadline siswa sudah tidak
     * bisa menjawab, tapi outbox offline masih boleh masuk sampai expires_at.
     */
    public function acceptsNewAnswers(?Carbon $now = null): bool
    {
        return $this->status === AttemptStatus::InProgress && ! $this->isPastDeadline($now);
    }

    /**
     * Persentase soal yang sudah dijawab, untuk progress bar monitoring.
     */
    public function progressPercent(): float
    {
        if ($this->total_questions <= 0) {
            return 0.0;
        }

        $answered = $this->answers()->count();

        return round(min(100, ($answered / $this->total_questions) * 100), 1);
    }
}

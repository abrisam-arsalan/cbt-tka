<?php

namespace App\Models;

use App\Enums\AntiCheatAction;
use App\Enums\AttemptStatus;
use App\Enums\ExamStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'title', 'description', 'duration_minutes', 'start_at', 'end_at', 'status', 'paused_at',
    'anti_cheat_enabled', 'anti_cheat_max_warnings', 'anti_cheat_action',
    'shuffle_questions', 'shuffle_options', 'offline_grace_minutes', 'created_by',
])]
class Exam extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'paused_at' => 'datetime',
            'status' => ExamStatus::class,
            'anti_cheat_enabled' => 'boolean',
            'anti_cheat_max_warnings' => 'integer',
            'anti_cheat_action' => AntiCheatAction::class,
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'duration_minutes' => 'integer',
            'offline_grace_minutes' => 'integer',
        ];
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ExamParticipant::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeStatus(Builder $query, ExamStatus|string $status): Builder
    {
        return $query->where('status', $status instanceof ExamStatus ? $status->value : $status);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ExamStatus::Active->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('start_at')->orderByDesc('id');
    }

    /**
     * Apakah sekarang berada di dalam jendela jadwal ujian.
     *
     * start_at / end_at yang null berarti tidak dibatasi pada sisi itu.
     */
    public function isWithinSchedule(?Carbon $now = null): bool
    {
        $now ??= now();

        if ($this->start_at !== null && $now->lt($this->start_at)) {
            return false;
        }

        if ($this->end_at !== null && $now->gt($this->end_at)) {
            return false;
        }

        return true;
    }

    /**
     * Siswa boleh mulai mengerjakan hanya bila ujian aktif DAN di dalam jadwal.
     */
    public function isJoinableNow(?Carbon $now = null): bool
    {
        return $this->status->isJoinable() && $this->isWithinSchedule($now);
    }

    /**
     * Alasan ujian tidak bisa diikuti, atau null bila bisa.
     *
     * Dipakai UI siswa supaya pesan yang tampil spesifik, bukan "tidak bisa".
     */
    public function unavailableReason(?Carbon $now = null): ?string
    {
        $now ??= now();

        return match (true) {
            $this->status === ExamStatus::Draft => 'Ujian masih berstatus draf dan belum diaktifkan admin.',
            $this->status === ExamStatus::Paused => 'Ujian sedang dijeda oleh admin. Silakan tunggu.',
            $this->status === ExamStatus::Completed => 'Ujian sudah ditutup.',
            $this->start_at !== null && $now->lt($this->start_at) => 'Ujian belum dimulai. Jadwal mulai: '.$this->start_at->format('d/m/Y H:i'),
            $this->end_at !== null && $now->gt($this->end_at) => 'Ujian sudah berakhir sejak '.$this->end_at->format('d/m/Y H:i'),
            default => null,
        };
    }

    /**
     * Syarat minimum sebelum ujian boleh diaktifkan.
     *
     * @return array<int, string> daftar masalah; kosong bila layak diaktifkan
     */
    public function activationBlockers(): array
    {
        $blockers = [];

        if ($this->duration_minutes < 1) {
            $blockers[] = 'Durasi ujian harus lebih dari 0 menit.';
        }

        if ($this->questions()->where('is_active', true)->doesntExist()) {
            $blockers[] = 'Ujian belum memiliki soal aktif.';
        }

        if ($this->participants()->where('is_active', true)->doesntExist()) {
            $blockers[] = 'Ujian belum memiliki peserta.';
        }

        if ($this->start_at !== null && $this->end_at !== null && $this->end_at->lte($this->start_at)) {
            $blockers[] = 'Waktu selesai harus setelah waktu mulai.';
        }

        return $blockers;
    }

    public function canActivate(): bool
    {
        return $this->activationBlockers() === [];
    }

    /**
     * Grace period efektif dalam menit, dengan fallback ke konfigurasi global.
     */
    public function graceMinutes(): int
    {
        $grace = (int) $this->offline_grace_minutes;

        return $grace > 0 ? $grace : (int) config('cbt.exam.default_offline_grace_minutes', 10);
    }

    public function maxWarnings(): int
    {
        $max = (int) $this->anti_cheat_max_warnings;

        return $max > 0 ? $max : (int) config('cbt.exam.default_anti_cheat_max_warnings', 3);
    }

    /**
     * Jumlah attempt yang belum selesai — dipakai dashboard monitoring.
     */
    public function activeAttemptCount(): int
    {
        return $this->attempts()
            ->whereIn('status', [AttemptStatus::InProgress->value, AttemptStatus::Locked->value])
            ->count();
    }
}

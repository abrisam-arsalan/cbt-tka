<?php

namespace App\Enums;

use App\Enums\Concerns\LabeledEnum;

/**
 * Siklus hidup sebuah attempt ujian.
 *
 * Alur normal:  in_progress -> submitted
 * Waktu habis:  in_progress -> expired -> submitted (oleh scheduler)
 * Ujian dijeda: in_progress -> locked -> in_progress (saat ujian dilanjutkan)
 */
enum AttemptStatus: string
{
    use LabeledEnum;

    case InProgress = 'in_progress';
    case Locked = 'locked';
    case Expired = 'expired';
    case Submitted = 'submitted';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Sedang dikerjakan',
            self::Locked => 'Terkunci',
            self::Expired => 'Kedaluwarsa',
            self::Submitted => 'Sudah dikumpulkan',
        };
    }

    public function labelShort(): string
    {
        return match ($this) {
            self::InProgress => 'Berjalan',
            self::Locked => 'Terkunci',
            self::Expired => 'Kedaluwarsa',
            self::Submitted => 'Selesai',
        };
    }

    /**
     * Attempt masih menerima jawaban dari siswa.
     */
    public function acceptsAnswers(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Attempt belum selesai dan masih jadi tanggung jawab siswa.
     */
    public function isActive(): bool
    {
        return $this === self::InProgress || $this === self::Locked;
    }

    public function isFinished(): bool
    {
        return $this === self::Submitted || $this === self::Expired;
    }
}

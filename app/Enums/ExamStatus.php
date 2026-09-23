<?php

namespace App\Enums;

use App\Enums\Concerns\LabeledEnum;

enum ExamStatus: string
{
    use LabeledEnum;

    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Active => 'Aktif',
            self::Paused => 'Dijeda',
            self::Completed => 'Selesai',
        };
    }

    /**
     * Ujian hanya boleh dikerjakan siswa saat berstatus active.
     */
    public function isJoinable(): bool
    {
        return $this === self::Active;
    }

    /**
     * Status yang masih mengizinkan attempt berjalan.
     */
    public function isOpen(): bool
    {
        return $this === self::Active || $this === self::Paused;
    }
}

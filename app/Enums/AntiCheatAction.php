<?php

namespace App\Enums;

use App\Enums\Concerns\LabeledEnum;

/**
 * Tindakan yang diambil server saat warnings_count siswa mencapai
 * anti_cheat_max_warnings pada sebuah ujian.
 */
enum AntiCheatAction: string
{
    use LabeledEnum;

    /** Hanya tulis ke anti_cheat_logs, siswa tidak diganggu. */
    case LogOnly = 'log_only';

    /** Tulis log dan tampilkan modal peringatan, tanpa sanksi. */
    case WarningOnly = 'warning_only';

    /** Setelah batas terlampaui, attempt disubmit otomatis dan dinilai. */
    case AutoSubmitAfterLimit = 'auto_submit_after_limit';

    /** Setelah batas terlampaui, attempt dikunci dan tidak bisa dilanjutkan. */
    case LockAfterLimit = 'lock_after_limit';

    public function label(): string
    {
        return match ($this) {
            self::LogOnly => 'Hanya catat log',
            self::WarningOnly => 'Catat log + tampilkan peringatan',
            self::AutoSubmitAfterLimit => 'Auto submit setelah batas terlampaui',
            self::LockAfterLimit => 'Kunci attempt setelah batas terlampaui',
        };
    }

    public function labelShort(): string
    {
        return match ($this) {
            self::LogOnly => 'Log saja',
            self::WarningOnly => 'Peringatan',
            self::AutoSubmitAfterLimit => 'Auto submit',
            self::LockAfterLimit => 'Kunci',
        };
    }

    /**
     * Apakah siswa perlu diberi tahu lewat modal saat kembali ke halaman ujian.
     */
    public function showsWarningModal(): bool
    {
        return $this === self::WarningOnly
            || $this === self::AutoSubmitAfterLimit
            || $this === self::LockAfterLimit;
    }

    /**
     * Apakah pelanggaran di atas batas berakibat fatal pada attempt.
     */
    public function isEnforcing(): bool
    {
        return $this === self::AutoSubmitAfterLimit || $this === self::LockAfterLimit;
    }
}

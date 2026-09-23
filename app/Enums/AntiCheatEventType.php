<?php

namespace App\Enums;

use App\Enums\Concerns\LabeledEnum;

/**
 * Jenis kejadian yang dicatat ke anti_cheat_logs.
 */
enum AntiCheatEventType: string
{
    use LabeledEnum;

    /** Tab/window berpindah atau diminimalkan (event visibilitychange). */
    case VisibilityHidden = 'visibility_hidden';

    /** Jendela ujian kehilangan fokus (event blur). */
    case WindowBlur = 'window_blur';

    /** Siswa kembali ke halaman ujian setelah meninggalkannya. */
    case Returned = 'returned';

    /** warnings_count melewati anti_cheat_max_warnings. */
    case LimitExceeded = 'limit_exceeded';

    /** Sanksi diterapkan server (auto submit / lock). */
    case ActionTaken = 'action_taken';

    /** Catatan manual dari admin (mis. hasil reset warning). */
    case AdminAction = 'admin_action';

    public function label(): string
    {
        return match ($this) {
            self::VisibilityHidden => 'Berpindah tab / minimize',
            self::WindowBlur => 'Jendela kehilangan fokus',
            self::Returned => 'Kembali ke halaman ujian',
            self::LimitExceeded => 'Batas peringatan terlampaui',
            self::ActionTaken => 'Sanksi diterapkan',
            self::AdminAction => 'Tindakan admin',
        };
    }

    /**
     * Kejadian yang menambah warnings_count pada attempt.
     *
     * Hanya kejadian "meninggalkan ujian" yang dihitung sebagai pelanggaran.
     * Kejadian turunan (returned, limit_exceeded, action_taken) tidak boleh
     * menambah counter agar satu pelanggaran tidak dihitung dua kali.
     */
    public function incrementsWarning(): bool
    {
        return $this === self::VisibilityHidden || $this === self::WindowBlur;
    }
}

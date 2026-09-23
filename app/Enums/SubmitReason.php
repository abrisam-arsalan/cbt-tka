<?php

namespace App\Enums;

use App\Enums\Concerns\LabeledEnum;

/**
 * Alasan sebuah attempt berakhir dengan status submitted.
 *
 * Disimpan agar admin bisa membedakan siswa yang mengumpulkan sendiri
 * dari yang disubmit paksa oleh sistem — penting untuk audit dan sengketa nilai.
 */
enum SubmitReason: string
{
    use LabeledEnum;

    /** Siswa menekan tombol submit. */
    case Manual = 'manual';

    /** Waktu pengerjaan siswa habis (scheduler auto submit). */
    case TimeUp = 'time_up';

    /** Admin menutup ujian dengan aksi "Tutup & Auto Submit". */
    case ExamClosed = 'exam_closed';

    /** Batas peringatan anti-cheat terlampaui. */
    case AntiCheatLimit = 'anti_cheat_limit';

    /** Admin memaksa submit attempt tertentu dari halaman monitoring. */
    case AdminForce = 'admin_force';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Submit oleh siswa',
            self::TimeUp => 'Waktu habis (auto submit)',
            self::ExamClosed => 'Ujian ditutup admin (auto submit)',
            self::AntiCheatLimit => 'Batas anti-cheat terlampaui',
            self::AdminForce => 'Disubmit paksa oleh admin',
        };
    }
}

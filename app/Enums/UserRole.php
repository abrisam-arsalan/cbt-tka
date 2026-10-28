<?php

namespace App\Enums;

use App\Enums\Concerns\LabeledEnum;

enum UserRole: string
{
    use LabeledEnum;

    case Admin = 'admin';
    case Siswa = 'siswa';
    // PRD v2 §4.3: guru boleh bank soal + lihat hasil (mapelnya saja).
    // Nilai 'guru' — migration penambah enum menyusul di P7.
    case Guru = 'guru';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Siswa => 'Siswa',
            self::Guru => 'Guru',
        };
    }

    public function isAdmin(): bool
    {
        return $this === self::Admin;
    }

    public function isSiswa(): bool
    {
        return $this === self::Siswa;
    }

    public function isGuru(): bool
    {
        return $this === self::Guru;
    }
}

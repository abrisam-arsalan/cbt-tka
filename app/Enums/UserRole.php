<?php

namespace App\Enums;

use App\Enums\Concerns\LabeledEnum;

enum UserRole: string
{
    use LabeledEnum;

    case Admin = 'admin';
    case Siswa = 'siswa';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Siswa => 'Siswa',
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
}

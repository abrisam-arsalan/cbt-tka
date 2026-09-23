<?php

namespace App\Enums\Concerns;

/**
 * Menyediakan helper label + opsi untuk seluruh enum CBT.
 *
 * Dipakai agar Vue bisa menerima daftar pilihan berlabel Bahasa Indonesia
 * tanpa perlu menerjemahkan ulang di frontend.
 */
trait LabeledEnum
{
    abstract public function label(): string;

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn ($case) => ['value' => $case->value, 'label' => $case->label()],
            static::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(static::options(), 'value');
    }

    /**
     * Peta value => label, berguna untuk menampilkan badge di tabel.
     *
     * @return array<string, string>
     */
    public static function map(): array
    {
        return array_column(static::options(), 'label', 'value');
    }
}

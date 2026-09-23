<?php

namespace App\Enums;

use App\Enums\Concerns\LabeledEnum;

enum QuestionType: string
{
    use LabeledEnum;

    /** Pilihan ganda biasa — satu opsi benar. */
    case Pg = 'pg';

    /** Pilihan ganda kompleks — beberapa opsi benar, dinilai sebagai satu paket. */
    case Pgk = 'pgk';

    /** Benar / salah. */
    case Boolean = 'boolean';

    /** Menjodohkan — dinilai all-or-nothing. */
    case Matching = 'matching';

    public function label(): string
    {
        return match ($this) {
            self::Pg => 'Pilihan Ganda',
            self::Pgk => 'Pilihan Ganda Kompleks',
            self::Boolean => 'Benar / Salah',
            self::Matching => 'Menjodohkan',
        };
    }

    public function labelShort(): string
    {
        return match ($this) {
            self::Pg => 'PG',
            self::Pgk => 'PG Kompleks',
            self::Boolean => 'B/S',
            self::Matching => 'Menjodohkan',
        };
    }

    /**
     * Tipe yang memakai tabel options.
     */
    public function usesOptions(): bool
    {
        return $this === self::Pg || $this === self::Pgk;
    }

    /**
     * Tipe yang memakai tabel matching_pairs.
     */
    public function usesPairs(): bool
    {
        return $this === self::Matching;
    }

    /**
     * Jumlah opsi benar yang diharapkan.
     */
    public function expectedCorrectOptions(): ?int
    {
        return match ($this) {
            self::Pg => 1,
            default => null,
        };
    }

    /**
     * Petunjuk pengerjaan yang ditampilkan ke siswa.
     */
    public function instruction(): string
    {
        return match ($this) {
            self::Pg => 'Pilih satu jawaban yang paling tepat.',
            self::Pgk => 'Pilih semua pernyataan yang benar, lalu pastikan jawaban terkunci. Soal dinilai benar hanya bila seluruh pilihan tepat.',
            self::Boolean => 'Tentukan apakah pernyataan berikut Benar atau Salah.',
            self::Matching => 'Pasangkan setiap item di kiri dengan pasangan yang tepat di kanan. Soal dinilai benar hanya bila seluruh pasangan tepat.',
        };
    }
}

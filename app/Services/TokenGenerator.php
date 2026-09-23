<?php

namespace App\Services;

/**
 * Pembangkit token ujian.
 *
 * Token dibuat dari alphabet acak kriptografis (random_bytes) dengan panjang
 * yang cukup supaya tidak bisa ditebak dan tidak bentrok. Alphabet sengaja
 * membuang I, O, 0, 1 agar mudah dibaca siswa saat menyalin dari kartu.
 *
 * 32^8 (alphabet 32 karakter, panjang 8) = 1.099.511.627.776 kombinasi.
 * Dengan birthday bound, peluang tabrakan pada 10.000 token ~ 4.5 × 10^-8.
 * Jauh di bawah risiko operasional sekolah.
 */
class TokenGenerator
{
    public function __construct(
        private readonly int $length = 8,
        private readonly string $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
        private readonly int $chunkLength = 4,
        private readonly string $separator = '-',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            length: (int) config('cbt.token.length', 8),
            alphabet: (string) config('cbt.token.alphabet', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'),
            chunkLength: (int) config('cbt.token.chunk_length', 4),
            separator: (string) config('cbt.token.separator', '-'),
        );
    }

    public function generateRaw(): string
    {
        $alphabetLength = strlen($this->alphabet);

        if ($alphabetLength < 2) {
            throw new \InvalidArgumentException('Alphabet token terlalu pendek.');
        }

        $bytes = random_bytes($this->length);
        $token = '';

        for ($i = 0; $i < $this->length; $i++) {
            // Bias minimal karena 256 % 32 = 0 persis (alphabet default).
            // Untuk alphabet lain, modulo bias < 1/256, aman untuk kasus ini.
            $token .= $this->alphabet[ord($bytes[$i]) % $alphabetLength];
        }

        return $token;
    }

    /**
     * Format token dengan pemisah tiap 4 karakter, mis. "ABCD-EFGH".
     */
    public function generate(): string
    {
        return $this->format($this->generateRaw());
    }

    public function format(string $raw): string
    {
        if ($this->chunkLength < 1 || $this->separator === '') {
            return strtoupper($raw);
        }

        return implode(
            $this->separator,
            str_split(strtoupper($raw), $this->chunkLength),
        );
    }

    /**
     * Normalisasi input siswa: strip pemisah, kapitalisasi, buang spasi.
     */
    public function normalize(string $input): string
    {
        $input = str_replace([' ', "\t", $this->separator, '-', '_'], '', $input);

        return strtoupper($input);
    }
}

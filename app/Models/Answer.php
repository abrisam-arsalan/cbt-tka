<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu jawaban final untuk satu soal dalam satu attempt.
 *
 * Baris ini di-UPDATE (bukan di-INSERT baru) setiap kali siswa mengubah
 * jawaban. Keunikan (attempt_id, question_id) dijaga oleh index unik di
 * database, jadi invariant "satu jawaban final" tidak bisa dilanggar bahkan
 * oleh kode yang salah atau request balapan.
 */
#[Fillable([
    'attempt_id', 'question_id', 'answer_payload',
    'idempotency_key', 'client_seq', 'late_sync_flag',
])]
class Answer extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'answer_payload' => 'array',
            'client_seq' => 'integer',
            'late_sync_flag' => 'boolean',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function scopeLate(Builder $query): Builder
    {
        return $query->where('late_sync_flag', true);
    }

    /**
     * ID opsi yang dipilih siswa (tipe pg).
     */
    public function selectedOptionId(): ?int
    {
        $value = $this->answer_payload['option_id'] ?? null;

        return $value === null ? null : (int) $value;
    }

    /**
     * Himpunan ID opsi yang dipilih siswa (tipe pgk), sudah dinormalisasi.
     *
     * @return array<int, int>
     */
    public function selectedOptionIds(): array
    {
        $raw = $this->answer_payload['option_ids'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $ids = array_map('intval', $raw);
        $ids = array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));

        sort($ids);

        return $ids;
    }

    /**
     * Nilai benar/salah yang dipilih siswa (tipe boolean).
     */
    public function booleanValue(): ?bool
    {
        $value = $this->answer_payload['value'] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * Peta jawaban menjodohkan: id_kiri => id_pasangan_yang_dipilih.
     *
     * @return array<int, int>
     */
    public function matchingMap(): array
    {
        $raw = $this->answer_payload['mapping'] ?? [];

        if (! is_array($raw)) {
            return [];
        }

        $map = [];

        foreach ($raw as $left => $right) {
            $left = (int) $left;
            $right = (int) $right;

            if ($left > 0 && $right > 0) {
                $map[$left] = $right;
            }
        }

        return $map;
    }

    /**
     * Apakah siswa benar-benar memberi jawaban, bukan payload kosong.
     */
    public function isBlank(): bool
    {
        $payload = $this->answer_payload;

        if (! is_array($payload) || $payload === []) {
            return true;
        }

        return match (true) {
            array_key_exists('option_id', $payload) => $payload['option_id'] === null,
            array_key_exists('option_ids', $payload) => ! is_array($payload['option_ids']) || $payload['option_ids'] === [],
            array_key_exists('value', $payload) => $payload['value'] === null,
            array_key_exists('mapping', $payload) => ! is_array($payload['mapping']) || $payload['mapping'] === [],
            default => true,
        };
    }
}

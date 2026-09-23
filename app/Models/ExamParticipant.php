<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'exam_id', 'user_id', 'token_hash', 'token_cipher',
    'token_generated_at', 'is_active', 'first_joined_at',
])]
#[Hidden(['token_hash', 'token_cipher'])]
class ExamParticipant extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'token_generated_at' => 'datetime',
            'first_joined_at' => 'datetime',
            'is_active' => 'boolean',
            // Disimpan terenkripsi dengan APP_KEY sehingga token tetap bisa
            // dicetak ulang untuk kartu ujian, tapi tidak terbaca bila file
            // database bocor.
            'token_cipher' => 'encrypted',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function attempt(): HasOne
    {
        return $this->hasOne(Attempt::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Token plaintext untuk dicetak di kartu ujian.
     *
     * Mengembalikan null bila token belum pernah digenerate atau gagal
     * didekripsi (mis. APP_KEY pernah diganti).
     */
    public function plainToken(): ?string
    {
        $token = $this->token_cipher;

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function hasToken(): bool
    {
        return $this->token_hash !== null && $this->token_hash !== '';
    }
}

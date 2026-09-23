<?php

namespace App\Services\Sync;

/**
 * Alasan sebuah item sinkronisasi tidak diterapkan.
 *
 * "retryable" menentukan perilaku klien:
 *   true  -> jawaban TETAP di outbox browser dan dicoba lagi nanti.
 *            Dipakai untuk kondisi sementara (ujian dijeda admin).
 *   false -> jawaban dibuang dari outbox karena percuma diulang.
 *
 * Membedakan keduanya penting agar jawaban siswa tidak hilang saat admin
 * menjeda ujian di tengah pengerjaan.
 */
enum RejectCode: string
{
    /** Soal bukan bagian dari ujian attempt ini. */
    case QuestionNotInExam = 'question_not_in_exam';

    /** Attempt milik siswa lain. */
    case AttemptNotOwned = 'attempt_not_owned';

    /** Attempt sudah final dan dinilai. */
    case AttemptSubmitted = 'attempt_submitted';

    /** Attempt terkunci: ujian dijeda admin atau dikunci anti-cheat. */
    case AttemptLocked = 'attempt_locked';

    /** Grace period offline sudah habis. */
    case GracePeriodEnded = 'grace_period_ended';

    /** Bentuk answer_payload tidak sesuai tipe soal atau berisi id asing. */
    case InvalidPayload = 'invalid_payload';

    /** Ada item dengan question_id atau idempotency_key ganda dalam satu request. */
    case MalformedBatch = 'malformed_batch';

    public function retryable(): bool
    {
        return match ($this) {
            self::AttemptLocked => true,
            default => false,
        };
    }

    public function message(): string
    {
        return match ($this) {
            self::QuestionNotInExam => 'Soal tidak termasuk dalam ujian ini.',
            self::AttemptNotOwned => 'Attempt ini bukan milik Anda.',
            self::AttemptSubmitted => 'Jawaban sudah dikumpulkan dan tidak dapat diubah.',
            self::AttemptLocked => 'Ujian sedang dikunci. Jawaban disimpan di perangkat dan akan dikirim ulang otomatis.',
            self::GracePeriodEnded => 'Batas waktu pengiriman jawaban sudah lewat.',
            self::InvalidPayload => 'Format jawaban tidak valid untuk tipe soal ini.',
            self::MalformedBatch => 'Permintaan sinkronisasi tidak valid.',
        };
    }
}

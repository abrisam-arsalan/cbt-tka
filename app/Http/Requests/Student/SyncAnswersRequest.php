<?php

namespace App\Http\Requests\Student;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi payload sinkronisasi jawaban dari outbox offline.
 *
 * Validasi dilakukan dua lapis: di sini untuk bentuk request, dan di
 * AnswerSyncService untuk invarian bisnis (kepemilikan attempt,
 * deadline/grace period, dan id milik soal).
 */
class SyncAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $maxBatch = (int) config('cbt.throttle.max_sync_batch', 50);

        return [
            'items' => ['required', 'array', 'min:1', "max:{$maxBatch}"],
            'items.*.question_id' => ['required', 'integer', 'distinct'],
            'items.*.idempotency_key' => ['required', 'string', 'max:80', 'distinct'],
            'items.*.client_seq' => ['required', 'integer', 'min:0', 'max:4294967295'],
            // "present" agar klien boleh mengirim null eksplisit saat mengosongkan jawaban.
            'items.*.answer_payload' => ['present', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Tidak ada jawaban yang dikirim.',
            'items.max' => 'Terlalu banyak jawaban dalam satu request.',
            'items.*.idempotency_key.distinct' => 'Terdapat idempotency_key yang duplikat dalam satu request.',
            'items.*.question_id.distinct' => 'Terdapat soal yang dikirim dua kali dalam satu request.',
        ];
    }
}

<?php

namespace App\Services\Sync;

/**
 * Hasil pemrosesan satu item jawaban.
 *
 * @param  string  $status  accepted | duplicate | stale | rejected
 * @param  string|null  $detail  created | updated | RejectCode value
 */
final class SyncItemResult
{
    public function __construct(
        public readonly int $questionId,
        public readonly string $idempotencyKey,
        public readonly int $clientSeq,
        public readonly string $status,
        public readonly ?string $detail = null,
        public readonly bool $lateSync = false,
        public readonly bool $retryable = false,
        public readonly ?string $message = null,
    ) {}

    public static function accepted(
        int $questionId,
        string $idempotencyKey,
        int $clientSeq,
        string $detail,
        bool $lateSync,
    ): self {
        return new self(
            questionId: $questionId,
            idempotencyKey: $idempotencyKey,
            clientSeq: $clientSeq,
            status: 'accepted',
            detail: $detail,
            lateSync: $lateSync,
        );
    }

    /**
     * Request ini sudah pernah diproses. Klien boleh membuang item dari outbox.
     */
    public static function duplicate(int $questionId, string $idempotencyKey, int $clientSeq): self
    {
        return new self(
            questionId: $questionId,
            idempotencyKey: $idempotencyKey,
            clientSeq: $clientSeq,
            status: 'duplicate',
            detail: 'duplicate',
        );
    }

    /**
     * Ada versi jawaban yang lebih baru di server. Klien membuang item ini.
     */
    public static function stale(int $questionId, string $idempotencyKey, int $clientSeq, int $serverSeq): self
    {
        return new self(
            questionId: $questionId,
            idempotencyKey: $idempotencyKey,
            clientSeq: $clientSeq,
            status: 'stale',
            detail: 'stale_sequence',
            message: "Jawaban lebih baru (seq {$serverSeq}) sudah tersimpan di server.",
        );
    }

    public static function rejected(
        int $questionId,
        string $idempotencyKey,
        int $clientSeq,
        RejectCode $code,
    ): self {
        return new self(
            questionId: $questionId,
            idempotencyKey: $idempotencyKey,
            clientSeq: $clientSeq,
            status: 'rejected',
            detail: $code->value,
            retryable: $code->retryable(),
            message: $code->message(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'question_id' => $this->questionId,
            'idempotency_key' => $this->idempotencyKey,
            'client_seq' => $this->clientSeq,
            'status' => $this->status,
            'detail' => $this->detail,
            'late_sync' => $this->lateSync,
            'retryable' => $this->retryable,
            'message' => $this->message,
        ];
    }
}

<?php

namespace App\Services\Sync;

/**
 * Hasil satu request sinkronisasi.
 *
 * @param  array<int, SyncItemResult>  $items
 */
final class SyncResult
{
    public function __construct(
        public readonly array $items,
        public readonly string $attemptStatus,
        public readonly int $remainingSeconds,
        public readonly int $serverTime,
        public readonly ?string $attemptMessage = null,
        public readonly bool $deadlinePassed = false,
        public readonly bool $graceEnded = false,
    ) {}

    public function acceptedCount(): int
    {
        return count(array_filter($this->items, fn (SyncItemResult $i) => $i->status === 'accepted'));
    }

    public function duplicateCount(): int
    {
        return count(array_filter($this->items, fn (SyncItemResult $i) => $i->status === 'duplicate'));
    }

    public function staleCount(): int
    {
        return count(array_filter($this->items, fn (SyncItemResult $i) => $i->status === 'stale'));
    }

    /**
     * Item yang harus tetap disimpan di outbox dan dicoba lagi nanti.
     */
    public function retryableKeys(): array
    {
        return array_values(array_map(
            fn (SyncItemResult $i) => $i->idempotencyKey,
            array_filter($this->items, fn (SyncItemResult $i) => $i->status === 'rejected' && $i->retryable),
        ));
    }

    public function rejectedCount(): int
    {
        return count(array_filter($this->items, fn (SyncItemResult $i) => $i->status === 'rejected'));
    }

    public function hasLateSync(): bool
    {
        foreach ($this->items as $item) {
            if ($item->lateSync) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'accepted' => $this->acceptedCount(),
            'duplicated' => $this->duplicateCount(),
            'stale' => $this->staleCount(),
            'rejected' => $this->rejectedCount(),
            'retryable_keys' => $this->retryableKeys(),
            'late_sync' => $this->hasLateSync(),
            'deadline_passed' => $this->deadlinePassed,
            'grace_ended' => $this->graceEnded,
            'attempt_status' => $this->attemptStatus,
            'attempt_message' => $this->attemptMessage,
            'remaining_seconds' => $this->remainingSeconds,
            'server_time' => $this->serverTime,
            'items' => array_map(fn (SyncItemResult $i) => $i->toArray(), $this->items),
        ];
    }
}

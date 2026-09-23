<?php

namespace App\Services\Presence;

use App\Models\Attempt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * Presence berbasis Redis.
 *
 * Struktur data:
 *   ZSET cbt:presence:exam:{examId}   member = attemptId, score = timestamp terakhir
 *   HASH cbt:presence:meta:{attemptId} field status, user_id, last_seen_at, dsb.
 *
 * Alasan memakai sorted set: query "siapa yang online di ujian X" menjadi satu
 * perintah ZRANGEBYSCORE dengan batas bawah (now - ttl), tanpa perlu memindai
 * seluruh anggota. Data basi dibuang sekaligus dengan ZREMRANGEBYSCORE.
 *
 * Hash meta diberi TTL agar Redis membersihkan dirinya sendiri walaupun
 * scheduler prune tidak berjalan.
 */
class RedisPresenceDriver implements PresenceDriver
{
    private const META_TTL_MULTIPLIER = 6;

    public function name(): string
    {
        return 'redis';
    }

    /**
     * Probe ketersediaan Redis, di-cache singkat.
     *
     * Tanpa cache, Redis yang mati membuat setiap request menunggu timeout
     * koneksi (2 detik sesuai config/database.php) — cukup untuk membuat
     * 200 siswa mengalami halaman yang menggantung.
     */
    public static function isAvailable(): bool
    {
        return (bool) Cache::remember('cbt:presence:redis-probe', 60, function () {
            try {
                Redis::connection()->command('ping');

                return true;
            } catch (Throwable $e) {
                Log::warning('Redis tidak tersedia, presence fallback ke database.', [
                    'error' => $e->getMessage(),
                ]);

                return false;
            }
        });
    }

    public function ping(Attempt $attempt, array $meta = []): void
    {
        $now = now();
        $metaKey = $this->metaKey($attempt->id);

        Redis::pipeline(function ($pipe) use ($attempt, $meta, $now, $metaKey) {
            $pipe->zadd($this->examKey($attempt->exam_id), $now->getTimestamp(), (string) $attempt->id);

            $pipe->hMSet($metaKey, [
                'attempt_id' => (string) $attempt->id,
                'exam_id' => (string) $attempt->exam_id,
                'user_id' => (string) $attempt->user_id,
                'status' => (string) ($meta['status'] ?? 'online'),
                'last_seen_at' => $now->toIso8601String(),
                'last_client_seq' => (string) (int) ($meta['last_client_seq'] ?? 0),
                'outbox_pending' => (string) (int) ($meta['outbox_pending'] ?? 0),
                'ip_address' => (string) ($meta['ip_address'] ?? ''),
            ]);

            $pipe->expire($metaKey, $this->metaTtl());
        });
    }

    public function forget(Attempt $attempt): void
    {
        Redis::pipeline(function ($pipe) use ($attempt) {
            $pipe->zrem($this->examKey($attempt->exam_id), (string) $attempt->id);
            $pipe->del($this->metaKey($attempt->id));
        });
    }

    public function statesForExam(int $examId): array
    {
        $cutoff = $this->onlineCutoff()->getTimestamp();

        // Buang anggota basi lebih dulu supaya hasilnya tidak berisi data lama.
        Redis::zremrangebyscore($this->examKey($examId), '-inf', (string) ($cutoff - 1));

        /** @var array<int, string> $attemptIds */
        $attemptIds = Redis::zrangebyscore($this->examKey($examId), (string) $cutoff, '+inf');

        if ($attemptIds === []) {
            return [];
        }

        $keys = array_map(fn ($id) => $this->metaKey((int) $id), $attemptIds);

        /** @var array<int, array<string, string>|false> $metas */
        $metas = Redis::mget($keys);

        $states = [];

        foreach ($attemptIds as $index => $attemptId) {
            $meta = $metas[$index] ?? null;

            $states[(int) $attemptId] = [
                'attempt_id' => (int) $attemptId,
                'user_id' => isset($meta['user_id']) ? (int) $meta['user_id'] : null,
                'status' => $meta['status'] ?? 'online',
                'last_seen_at' => $meta['last_seen_at'] ?? null,
                'last_client_seq' => isset($meta['last_client_seq']) ? (int) $meta['last_client_seq'] : 0,
                'outbox_pending' => isset($meta['outbox_pending']) ? (int) $meta['outbox_pending'] : 0,
                'ip_address' => ($meta['ip_address'] ?? '') !== '' ? $meta['ip_address'] : null,
                'online' => true,
            ];
        }

        return $states;
    }

    public function isOnline(int $attemptId): bool
    {
        return $this->lastSeen($attemptId)?->greaterThanOrEqualTo($this->onlineCutoff()) ?? false;
    }

    public function lastSeen(int $attemptId): ?Carbon
    {
        $value = Redis::hget($this->metaKey($attemptId), 'last_seen_at');

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    public function prune(Carbon $olderThan): int
    {
        // Tidak ada daftar ujian yang bisa diiterasi tanpa memindai keyspace,
        // jadi pembersihan mengandalkan TTL pada hash meta dan ZREMRANGEBYSCORE
        // yang dipanggil setiap kali statesForExam() dijalankan.
        return 0;
    }

    private function examKey(int $examId): string
    {
        return "cbt:presence:exam:{$examId}";
    }

    private function metaKey(int $attemptId): string
    {
        return "cbt:presence:meta:{$attemptId}";
    }

    private function metaTtl(): int
    {
        return (int) config('cbt.presence.online_seconds', 45) * self::META_TTL_MULTIPLIER;
    }

    private function onlineCutoff(): Carbon
    {
        return now()->subSeconds((int) config('cbt.presence.online_seconds', 45));
    }
}

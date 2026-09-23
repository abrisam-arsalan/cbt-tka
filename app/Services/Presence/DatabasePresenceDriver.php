<?php

namespace App\Services\Presence;

use App\Models\Attempt;
use App\Models\PresenceHeartbeat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Fallback presence berbasis database.
 *
 * CATATAN PERFORMA untuk server sekolah (Windows 10 Pro, HDD, 200 siswa):
 *
 *  1. Satu baris per attempt, bukan satu baris per heartbeat.
 *     unique(attempt_id) + upsert membuat ukuran tabel konstan sebesar jumlah
 *     attempt, sehingga index muat di buffer pool InnoDB dan tidak ada
 *     pembengkakan file di HDD.
 *
 *  2. Upsert memakai INSERT ... ON DUPLICATE KEY UPDATE lewat query builder.
 *     Ini satu round-trip per heartbeat. Dengan 200 siswa dan interval 20 detik
 *     bebannya ~10 tulis/detik — ringan untuk MariaDB di HDD.
 *
 *  3. Kolom "meta" hanya diisi bila benar-benar perlu. Payload JSON besar akan
 *     memperbesar halaman InnoDB dan memperlambat tulis.
 *
 *  4. Baris basi dibersihkan scheduler (prune) agar tabel tidak tumbuh tanpa batas.
 */
class DatabasePresenceDriver implements PresenceDriver
{
    public function name(): string
    {
        return 'database';
    }

    public function ping(Attempt $attempt, array $meta = []): void
    {
        $now = now();

        $row = [
            'exam_id' => $attempt->exam_id,
            'attempt_id' => $attempt->id,
            'user_id' => $attempt->user_id,
            'status' => (string) ($meta['status'] ?? 'online'),
            'last_seen_at' => $now,
            'last_client_seq' => (int) ($meta['last_client_seq'] ?? 0),
            'outbox_pending' => (int) ($meta['outbox_pending'] ?? 0),
            'ip_address' => isset($meta['ip_address']) ? substr((string) $meta['ip_address'], 0, 45) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        // "meta" opsional: hanya ditulis bila ada isinya agar baris tetap ramping.
        $payloadMeta = array_diff_key($meta, array_flip([
            'status', 'last_client_seq', 'outbox_pending', 'ip_address',
        ]));

        if ($payloadMeta !== []) {
            $row['meta'] = json_encode($payloadMeta, JSON_UNESCAPED_UNICODE);
        }

        DB::table('presence_heartbeats')->upsert(
            [$row],
            ['attempt_id'],
            ['status', 'last_seen_at', 'last_client_seq', 'outbox_pending', 'ip_address', 'meta', 'updated_at'],
        );
    }

    public function forget(Attempt $attempt): void
    {
        DB::table('presence_heartbeats')->where('attempt_id', $attempt->id)->delete();
    }

    public function statesForExam(int $examId): array
    {
        $cutoff = $this->onlineCutoff();

        return PresenceHeartbeat::query()
            ->where('exam_id', $examId)
            ->where('last_seen_at', '>=', $cutoff)
            ->get()
            ->mapWithKeys(fn (PresenceHeartbeat $row) => [
                (int) $row->attempt_id => [
                    'attempt_id' => (int) $row->attempt_id,
                    'user_id' => (int) $row->user_id,
                    'status' => $row->status,
                    'last_seen_at' => $row->last_seen_at?->toIso8601String(),
                    'last_client_seq' => (int) $row->last_client_seq,
                    'outbox_pending' => (int) $row->outbox_pending,
                    'ip_address' => $row->ip_address,
                    'online' => true,
                ],
            ])
            ->all();
    }

    public function isOnline(int $attemptId): bool
    {
        return PresenceHeartbeat::query()
            ->where('attempt_id', $attemptId)
            ->where('last_seen_at', '>=', $this->onlineCutoff())
            ->exists();
    }

    public function lastSeen(int $attemptId): ?Carbon
    {
        /** @var string|null $value */
        $value = DB::table('presence_heartbeats')
            ->where('attempt_id', $attemptId)
            ->value('last_seen_at');

        return $value === null ? null : Carbon::parse($value);
    }

    public function prune(Carbon $olderThan): int
    {
        return DB::table('presence_heartbeats')
            ->where('last_seen_at', '<', $olderThan)
            ->delete();
    }

    private function onlineCutoff(): Carbon
    {
        return now()->subSeconds((int) config('cbt.presence.online_seconds', 45));
    }
}

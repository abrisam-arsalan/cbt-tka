<?php

namespace App\Services\Presence;

use App\Models\Attempt;
use Illuminate\Support\Carbon;

/**
 * Kontrak driver presence siswa.
 *
 * Dua implementasi tersedia:
 *  - RedisPresenceDriver    : sorted set + hash, paling ringan untuk tulis sering
 *  - DatabasePresenceDriver : fallback resmi, upsert ke tabel presence_heartbeats
 *
 * Keduanya harus berperilaku identik dari sudut pandang pemanggil, sehingga
 * aplikasi tetap berfungsi penuh tanpa Redis.
 */
interface PresenceDriver
{
    /**
     * Nama driver, dipakai untuk ditampilkan di dashboard admin.
     */
    public function name(): string;

    /**
     * Catat bahwa siswa masih aktif mengerjakan ujian.
     *
     * @param  array<string, mixed>  $meta
     */
    public function ping(Attempt $attempt, array $meta = []): void;

    /**
     * Hapus jejak presence (dipakai saat attempt disubmit atau dikunci).
     */
    public function forget(Attempt $attempt): void;

    /**
     * Status presence seluruh attempt pada satu ujian.
     *
     * @return array<int, array<string, mixed>> keyed by attempt id
     */
    public function statesForExam(int $examId): array;

    public function isOnline(int $attemptId): bool;

    public function lastSeen(int $attemptId): ?Carbon;

    /**
     * Bersihkan data presence yang sudah basi.
     *
     * @return int jumlah baris/key yang dibuang
     */
    public function prune(Carbon $olderThan): int;
}

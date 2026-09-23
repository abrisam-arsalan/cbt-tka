<?php

namespace App\Support;

/**
 * Menyaring konfigurasi yang aman dikirim ke browser.
 *
 * Hanya nilai non-rahasia yang mengontrol perilaku klien. Jangan pernah
 * menambahkan secret, kunci enkripsi, atau hash token ke daftar ini.
 */
class ClientConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function forBrowser(): array
    {
        return [
            'autosaveDebounceMs' => (int) config('cbt.client.autosave_debounce_ms', 1000),
            'outboxFlushIntervalMs' => (int) config('cbt.client.outbox_flush_interval_ms', 3000),
            'heartbeatIntervalSeconds' => (int) config('cbt.client.heartbeat_interval_seconds', 20),
            'presenceOnlineSeconds' => (int) config('cbt.presence.online_seconds', 45),
            'maxSyncBatch' => (int) config('cbt.throttle.max_sync_batch', 50),
            'tokenChunkLength' => (int) config('cbt.token.chunk_length', 4),
            'tokenSeparator' => (string) config('cbt.token.separator', '-'),
        ];
    }
}

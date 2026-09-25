<?php

/*
|--------------------------------------------------------------------------
| Konfigurasi CBT TKA Sekolah
|--------------------------------------------------------------------------
|
| Seluruh nilai yang mempengaruhi perilaku engine ujian dikumpulkan di sini
| agar bisa diubah lewat .env tanpa menyentuh kode.
|
*/

return [

    /*
    | Presence & heartbeat siswa.
    |
    | "driver" menentukan penyimpanan status online siswa:
    |   redis    -> pakai Redis (paling ringan untuk penulisan berfrekuensi tinggi)
    |   database -> fallback resmi, aman untuk 200 siswa concurrent
    */
    'presence' => [
        'driver' => env('CBT_PRESENCE_DRIVER', 'database'),
        'online_seconds' => (int) env('CBT_PRESENCE_ONLINE_SECONDS', 45),
    ],

    'heartbeat' => [
        'interval_seconds' => (int) env('CBT_HEARTBEAT_INTERVAL_SECONDS', 20),
    ],

    /*
    | Perilaku klien (browser siswa) saat mengerjakan ujian.
    */
    'client' => [
        'autosave_debounce_ms' => (int) env('CBT_AUTOSAVE_DEBOUNCE_MS', 1000),
        'outbox_flush_interval_ms' => (int) env('CBT_OUTBOX_FLUSH_INTERVAL_MS', 3000),
        'heartbeat_interval_seconds' => (int) env('CBT_HEARTBEAT_INTERVAL_SECONDS', 20),
    ],

    /*
    | Nilai default ujian.
    */
    'exam' => [
        'default_offline_grace_minutes' => (int) env('CBT_DEFAULT_OFFLINE_GRACE_MINUTES', 10),
        'default_anti_cheat_max_warnings' => (int) env('CBT_DEFAULT_ANTI_CHEAT_MAX_WARNINGS', 3),
    ],

    /*
    | Token ujian.
    |
    | Alphabet sengaja membuang I, O, 0, dan 1 supaya token mudah dibaca
    | saat siswa menyalin dari kartu ujian.
    */
    'token' => [
        'length' => (int) env('CBT_TOKEN_LENGTH', 8),
        'alphabet' => (string) env('CBT_TOKEN_ALPHABET', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'),
        'chunk_length' => 4,
        'separator' => '-',
    ],

    /*
    | Rate limiting.
    */
    'throttle' => [
        'login_max_attempts' => (int) env('CBT_LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_seconds' => (int) env('CBT_LOGIN_DECAY_SECONDS', 60),
        'max_sync_batch' => (int) env('CBT_MAX_SYNC_BATCH', 50),
    ],

    /*
    | Kartu ujian & QR code.
    */
    'card' => [
        'school_name' => (string) env('CBT_CARD_SCHOOL_NAME', env('APP_NAME', 'Panglima CBT')),
        'logo_path' => env('CBT_CARD_LOGO_PATH', 'images/logo.png'),
        'qr_size' => (int) env('CBT_CARD_QR_SIZE', 180),
    ],

    /*
    | Scheduler auto-submit.
    */
    'scheduler' => [
        'expire_sweep_chunk' => (int) env('CBT_EXPIRE_SWEEP_CHUNK', 200),
    ],

    /*
    | Keamanan.
    */
    'security' => [
        'force_https' => (bool) env('CBT_FORCE_HTTPS', false),
        'trusted_proxies' => env('TRUSTED_PROXIES', '*'),
    ],

];

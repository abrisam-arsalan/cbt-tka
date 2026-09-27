<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Pembungkus tabel settings.
 *
 * Menggunakan cache (CACHE_STORE=database/file) agar baca berulang dalam
 * satu request tidak menimbulkan banyak query kecil. Yang di-cache adalah
 * ARRAY DATA POLOS (key => value/group/is_public), BUKAN objek Eloquent —
 * Laravel 12+ menolak unserialize objek model dari cache file/database
 * (hasilnya __PHP_Incomplete_Class dan halaman jadi 500).
 *
 * Cache dipertahankan 5 menit — cukup singkat supaya perubahan admin
 * terasa cepat, cukup panjang untuk halaman monitoring 200 siswa.
 */
class SettingService
{
    private const CACHE_KEY = 'cbt.settings.all';
    private const CACHE_TTL_SECONDS = 300;

    /**
     * @param  array<string, mixed>  $defaults  nilai fallback bila key belum ada
     */
    public function get(string $key, mixed $default = null, array $defaults = []): mixed
    {
        $rows = $this->all();

        if (! array_key_exists($key, $rows)) {
            return $defaults[$key] ?? $default;
        }

        return $rows[$key]['value'];
    }

    public function set(string $key, mixed $value, string $type = 'string', array $extra = []): Setting
    {
        $setting = Setting::query()
            ->updateOrCreate(
                ['key' => $key],
                array_merge([
                    'value' => Setting::serialize($value, $type),
                    'type' => $type,
                ], $extra),
            );

        Cache::forget(self::CACHE_KEY);

        return $setting;
    }

    public function delete(string $key): void
    {
        Setting::query()->where('key', $key)->delete();
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Semua setting ter-cache sebagai data polos, di-key oleh `key`.
     *
     * @return array<string, array{value: mixed, group: string, is_public: bool}>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            $rows = [];

            foreach (Setting::query()->get() as $setting) {
                $rows[$setting->key] = [
                    'value' => $setting->typedValue(),
                    'group' => (string) $setting->group,
                    'is_public' => (bool) $setting->is_public,
                ];
            }

            return $rows;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $out = [];

        foreach ($this->all() as $key => $row) {
            if ($row['group'] === $group) {
                $out[$key] = $row['value'];
            }
        }

        return $out;
    }

    /**
     * Pengaturan yang boleh dikirim ke browser.
     *
     * @return array<string, mixed>
     */
    public function publicSettings(): array
    {
        $out = [];

        foreach ($this->all() as $key => $row) {
            if ($row['is_public']) {
                $out[$key] = $row['value'];
            }
        }

        return $out;
    }
}

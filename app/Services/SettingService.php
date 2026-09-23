<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Pembungkus tabel settings.
 *
 * Menggunakan cache database (CACHE_STORE=database) agar baca berulang
 * dalam satu request tidak menimbulkan banyak query kecil di HDD.
 * Cache dipertahankan selama 5 menit — cukup singkat supaya perubahan
 * admin terasa cepat, cukup panjang supaya halaman monitoring 200 siswa
 * tidak melakukan 200× baca settings.
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
        $setting = $this->all()[$key] ?? null;

        if ($setting === null) {
            return $defaults[$key] ?? $default;
        }

        return $setting->typedValue();
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
     * @return array<string, Setting> keyed by key
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return Setting::query()->get()->keyBy('key')->all();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function group(string $group): array
    {
        $out = [];

        foreach ($this->all() as $key => $setting) {
            if ($setting->group === $group) {
                $out[$key] = $setting->typedValue();
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

        foreach ($this->all() as $key => $setting) {
            if ((bool) $setting->is_public) {
                $out[$key] = $setting->typedValue();
            }
        }

        return $out;
    }
}

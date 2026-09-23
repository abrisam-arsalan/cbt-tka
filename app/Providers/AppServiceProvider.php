<?php

namespace App\Providers;

use App\Services\Presence\DatabasePresenceDriver;
use App\Services\Presence\PresenceDriver;
use App\Services\Presence\RedisPresenceDriver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Presence siswa: pakai Redis bila tersedia, kalau tidak fallback ke
        // tabel database. Keputusan diambil sekali per request lalu di-cache
        // singkat supaya Redis yang mati tidak memperlambat tiap halaman.
        $this->app->singleton(PresenceDriver::class, function () {
            $configured = (string) config('cbt.presence.driver', 'database');

            if ($configured === 'redis' && RedisPresenceDriver::isAvailable()) {
                return new RedisPresenceDriver;
            }

            return new DatabasePresenceDriver;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('cbt.security.force_https')) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    /**
     * Rate limiting per use-case.
     *
     * Batas untuk endpoint sinkronisasi sengaja longgar: saat koneksi siswa
     * pulih, browser mengirim seluruh isi outbox sekaligus. Bila dibatasi
     * terlalu ketat, jawaban yang tertahan justru ditolak dan hilang.
     */
    private function configureRateLimiting(): void
    {
        $maxLogin = (int) config('cbt.throttle.login_max_attempts', 5);
        $decayLogin = (int) config('cbt.throttle.login_decay_seconds', 60);

        RateLimiter::for('login', function (Request $request) use ($maxLogin, $decayLogin) {
            $username = strtolower(trim((string) $request->input('username')));

            return [
                // Per IP: menahan brute force terdistribusi.
                Limit::perMinutes(max(1, (int) ceil($decayLogin / 60)), $maxLogin * 4)
                    ->by($request->ip()),
                // Per kombinasi IP+username: menahan serangan tertarget pada
                // satu akun tanpa mengunci seluruh laboratorium komputer
                // yang berbagi satu IP publik.
                Limit::perMinutes(max(1, (int) ceil($decayLogin / 60)), $maxLogin)
                    ->by($request->ip().'|'.$username),
            ];
        });

        RateLimiter::for('exam-sync', function (Request $request) {
            return Limit::perMinute(240)->by($this->throttleKey($request));
        });

        RateLimiter::for('exam-heartbeat', function (Request $request) {
            return Limit::perMinute(60)->by($this->throttleKey($request));
        });

        RateLimiter::for('exam-join', function (Request $request) {
            return Limit::perMinute(20)->by($this->throttleKey($request));
        });

        RateLimiter::for('exam-anti-cheat', function (Request $request) {
            return Limit::perMinute(180)->by($this->throttleKey($request));
        });

        RateLimiter::for('admin', function (Request $request) {
            return Limit::perMinute(600)->by($this->throttleKey($request));
        });

        // Upload file impor soal dibatasi lebih ketat karena berat di HDD.
        RateLimiter::for('import', function (Request $request) {
            return Limit::perMinute(10)->by($this->throttleKey($request));
        });
    }

    /**
     * Kunci throttle: identitas user bila sudah login, selain itu IP.
     *
     * Memakai user id (bukan hanya IP) penting karena seluruh siswa di sekolah
     * bisa keluar lewat satu IP publik Cloudflare Tunnel.
     */
    private function throttleKey(Request $request): string
    {
        $user = $request->user();

        return $user
            ? 'user:'.$user->id
            : 'ip:'.(string) $request->ip();
    }
}

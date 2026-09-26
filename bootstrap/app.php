<?php

use App\Enums\UserRole;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::middleware('web')
                ->prefix('admin')
                ->name('admin.')
                ->group(base_path('routes/admin.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Server berdiri di belakang Cloudflare Tunnel (cloudflared di localhost).
        // Tanpa ini, $request->ip() selalu 127.0.0.1 sehingga rate-limit login
        // memakai satu bucket bersama untuk seluruh siswa, dan route() menghasilkan
        // URL http padahal publik mengakses https.
        //
        // WAJIB: batasi port origin lewat firewall Windows sehingga hanya cloudflared
        // dan LAN sekolah yang bisa mencapai server. Jika tidak, header
        // X-Forwarded-For bisa dipalsukan dari internet.
        //
        // Catatan: TrustProxies::at() adalah setter statis yang berjalan sebelum
        // config dan .env dimuat, jadi nilainya tidak boleh diambil dari config().
        $middleware->trustProxies(at: '*');

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);

        // Tamu yang mengakses area admin diarahkan ke pintu login admin
        // (/admin/login), bukan halaman login siswa — siswa tidak pernah
        // melihat adanya halaman khusus panitia.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('admin', 'admin/*')
            ? route('admin.login')
            : route('login'));
        $middleware->redirectUsersTo(function () {
            $user = auth()->user();

            if ($user !== null && $user->role === UserRole::Admin) {
                return route('admin.dashboard');
            }

            return route('student.dashboard');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*')
                || $request->expectsJson()
                // Endpoint sinkronisasi offline dipanggil fetch() dari service worker
                // outbox; response harus JSON agar klien bisa memutuskan retry.
                || $request->is('*/sync')
                || $request->is('*/heartbeat')
                || $request->is('*/anti-cheat'),
        );
    })->create();

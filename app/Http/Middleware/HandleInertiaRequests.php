<?php

namespace App\Http\Middleware;

use App\Support\ClientConfig;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'appName' => config('app.name'),

            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'username' => $request->user()->username,
                    'role' => $request->user()->role->value,
                    'role_label' => $request->user()->role->label(),
                    'class' => $request->user()->schoolClass?->only(['id', 'name', 'grade']),
                ] : null,
            ],

            // Flash message dari session untuk toast/banner di Vue.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
                'info' => fn () => $request->session()->get('info'),
            ],

            // Waktu server dipakai klien untuk menghitung selisih jam HP siswa.
            // Timer ujian harus otoritatif dari server, bukan dari jam perangkat.
            'serverTime' => now()->getTimestampMs(),
            'timezone' => config('app.timezone'),

            'clientConfig' => fn () => ClientConfig::forBrowser(),
        ];
    }
}

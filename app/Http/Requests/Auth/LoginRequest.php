<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login siswa dan admin memakai username (bukan email) karena mayoritas siswa
 * di sekolah tidak memiliki alamat email pribadi.
 */
class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    public function authenticate(): User
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt([
            'username' => $this->input('username'),
            'password' => $this->input('password'),
            'is_active' => true,
        ], (bool) $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Username atau password yang Anda masukkan salah, atau akun ini tidak aktif.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        $user = Auth::user();

        if ($user === null) {
            throw ValidationException::withMessages([
                'username' => 'Gagal memuat data akun. Silakan coba lagi.',
            ]);
        }

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), $this->maxAttempts())) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => "Terlalu banyak percobaan masuk. Silakan coba lagi dalam {$seconds} detik.",
        ]);
    }

    public function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->string('username')).'|'.(string) $this->ip(),
        );
    }

    private function maxAttempts(): int
    {
        return (int) config('cbt.throttle.login_max_attempts', 5);
    }
}

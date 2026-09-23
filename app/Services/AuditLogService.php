<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Pencatatan jejak tindakan admin.
 *
 * Dipanggil dari setiap aksi penting (ubah status ujian, force submit, reset
 * warning, hapus data, generate token, impor soal) sehingga admin bisa
 * mempertanggungjawabkan perubahan dan sengketa nilai bisa ditelusuri.
 */
class AuditLogService
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(
        string $action,
        ?Model $subject = null,
        ?string $description = null,
        array $meta = [],
        ?User $actor = null,
    ): AuditLog {
        $actor ??= Request::user();

        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'description' => $description,
            'meta' => $meta === [] ? null : $meta,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }

    /**
     * Catat perubahan data beserta nilai sebelum dan sesudahnya.
     *
     * Hanya kolom yang benar-benar berubah yang disimpan, supaya log tetap
     * ringkas dan mudah dibaca admin.
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  array<int, string>  $except  kolom yang tidak perlu dicatat
     */
    public function logChanges(
        string $action,
        Model $subject,
        array $before,
        array $after,
        ?string $description = null,
        array $except = ['updated_at', 'created_at'],
    ): AuditLog {
        $changes = [];

        foreach ($after as $key => $newValue) {
            if (in_array($key, $except, true)) {
                continue;
            }

            $oldValue = $before[$key] ?? null;

            if ($this->isDifferent($oldValue, $newValue)) {
                $changes[$key] = ['from' => $this->scalar($oldValue), 'to' => $this->scalar($newValue)];
            }
        }

        return $this->log(
            action: $action,
            subject: $subject,
            description: $description,
            meta: ['changes' => $changes],
        );
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function logFailure(string $action, ?Model $subject = null, string $reason = '', array $meta = []): AuditLog
    {
        return $this->log(
            action: $action.'.failed',
            subject: $subject,
            description: $reason !== '' ? $reason : null,
            meta: $meta,
        );
    }

    private function isDifferent(mixed $old, mixed $new): bool
    {
        // Perbandingan longgar untuk nilai yang berasal dari form (string "1")
        // dan dari database (integer 1).
        if (is_scalar($old) && is_scalar($new)) {
            return (string) $old !== (string) $new;
        }

        return $old !== $new;
    }

    private function scalar(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_array($value)) {
            return $value;
        }

        return (string) $value;
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Daftar mapel dari portal Panglima (GET /api/v1/mapel, Bearer token).
 * Hasil di-cache 1 jam; bila portal mati/tanpa token → fallback [] dan
 * halaman impor memakai input teks manual (ujian tidak boleh macet).
 */
class PortalSubjectService
{
    /**
     * @return array<int, array{id: string, nama: string, kode: string}>
     */
    public function list(): array
    {
        $base = rtrim((string) config('cbt.portal_base_url', ''), '/');
        $token = (string) config('cbt.portal_api_token', '');

        if ($base === '' || $token === '') {
            return [];
        }

        return Cache::remember('portal_subjects', 3600, function () use ($base, $token) {
            try {
                $res = Http::timeout(8)->withToken($token)->get($base.'/api/v1/mapel');
            } catch (\Throwable) {
                return [];
            }

            if (! $res->ok() || ! ($res->json('ok') ?? false)) {
                return [];
            }

            return collect($res->json('mapel', []))
                ->map(fn ($m) => [
                    'id' => (string) ($m['id'] ?? ''),
                    'nama' => (string) ($m['nama'] ?? ''),
                    'kode' => strtoupper(trim((string) ($m['kode'] ?? ''))),
                ])
                ->filter(fn ($m) => $m['id'] !== '' && $m['nama'] !== '')
                ->values()
                ->all();
        });
    }
}

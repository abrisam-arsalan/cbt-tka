<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Upload media soal (gambar) ke disk public.
 *
 * File disimpan di storage/app/public/soal/<tahun>/<bulan>/ dan disajikan
 * lewat symlink public/storage (php artisan storage:link — sudah dilakukan
 * deploy.sh). URL yang dikembalikan langsung diisi ke kolom media_url soal.
 */
class MediaController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,gif', 'max:4096', 'dimensions:min_width=16,min_height=16'],
        ], [
            'file.mimes' => 'Gambar harus berformat PNG, JPG, WEBP, atau GIF.',
            'file.max' => 'Ukuran gambar maksimum 4 MB.',
            'file.dimensions' => 'Ukuran gambar terlalu kecil (minimal 16x16 piksel).',
        ]);

        $file = $request->file('file');
        $path = $file->storeAs(
            'soal/'.now()->format('Y/m'),
            bin2hex(random_bytes(12)).'.'.$file->getClientOriginalExtension(),
            'public',
        );

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
        ]);
    }
}

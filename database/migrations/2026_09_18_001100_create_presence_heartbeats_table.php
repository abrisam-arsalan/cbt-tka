<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fallback presence/heartbeat berbasis database.
 *
 * Dipakai hanya bila CBT_PRESENCE_DRIVER=database atau Redis tidak bisa
 * dihubungi. Bila Redis tersedia, PresenceService menulis ke sorted set
 * Redis dan tabel ini dibiarkan kosong.
 *
 * CATATAN PERFORMA (server HDD, 200 siswa concurrent):
 *   unique(attempt_id) membuat setiap heartbeat menjadi UPSERT satu baris,
 *   bukan INSERT baru. Tanpa ini, 200 siswa x heartbeat tiap 20 detik
 *   menghasilkan ~864.000 baris per hari dan index membengkak di HDD.
 *   Dengan upsert, ukuran tabel tetap sebesar jumlah attempt aktif.
 *
 *   Kolom last_seen_at diindeks bersama exam_id karena query monitoring selalu
 *   berbentuk "siapa yang masih online pada ujian X".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presence_heartbeats', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // online | idle | offline
            $table->string('status', 20)->default('online')->index();

            $table->timestamp('last_seen_at')->index();

            // Sinkronisasi jawaban terakhir yang terlihat klien, dipakai
            // monitoring untuk mendeteksi siswa yang jawabannya tertahan
            // di outbox offline.
            $table->unsignedBigInteger('last_client_seq')->default(0);
            $table->unsignedSmallInteger('outbox_pending')->default(0);

            $table->string('ip_address', 45)->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique('attempt_id');
            $table->index(['exam_id', 'last_seen_at']);
            $table->index(['exam_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presence_heartbeats');
    }
};

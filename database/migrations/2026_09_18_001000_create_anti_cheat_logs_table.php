<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log kejadian anti-cheat.
 *
 * Tabel append-only: hanya created_at, tidak ada updated_at. Baris tidak
 * pernah diubah karena nilainya sebagai bukti justru terletak pada keutuhannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anti_cheat_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();

            // visibility_hidden | window_blur | returned | limit_exceeded
            // | action_taken | admin_action  (App\Enums\AntiCheatEventType)
            $table->string('type', 40)->index();

            $table->string('message')->nullable();

            // Konteks tambahan: user agent, durasi meninggalkan halaman,
            // jumlah peringatan saat kejadian, tindakan yang diambil, dsb.
            $table->json('meta')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['attempt_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anti_cheat_logs');
    }
};

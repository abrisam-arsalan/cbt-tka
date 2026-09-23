<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Peserta ujian beserta token aksesnya.
 *
 * Keamanan token:
 *  - token_hash  : SHA-256 dari token, dipakai untuk verifikasi dan lookup.
 *                  Ini satu-satunya kolom yang diindeks unik, jadi token tidak
 *                  pernah dibandingkan dalam bentuk plaintext di database.
 *  - token_cipher: token plaintext yang dienkripsi dengan APP_KEY (cast
 *                  "encrypted" pada model). Diperlukan karena kartu ujian harus
 *                  bisa dicetak ulang kapan saja oleh admin. Tanpa ini,
 *                  token hanya bisa dilihat sekali saat digenerate.
 *
 * Token digenerate dari alphabet acak kriptografis (lihat TokenGenerator),
 * jadi tidak bisa ditebak dan tidak berurutan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_participants', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('token_hash', 64)->unique();
            $table->text('token_cipher')->nullable();
            $table->timestamp('token_generated_at')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('first_joined_at')->nullable();

            $table->timestamps();

            // Satu siswa hanya terdaftar sekali pada satu ujian.
            $table->unique(['exam_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_participants');
    }
};

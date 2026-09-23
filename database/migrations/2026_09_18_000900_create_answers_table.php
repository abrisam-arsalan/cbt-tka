<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jawaban siswa — SATU jawaban final per soal per attempt.
 *
 * Invarian yang dijaga database, bukan hanya oleh kode:
 *
 *   unique(attempt_id, question_id)
 *       Menjamin tidak pernah ada dua baris jawaban untuk soal yang sama.
 *       Menjawab ulang berarti UPDATE baris yang sudah ada.
 *
 *   unique(idempotency_key)
 *       Menjamin request sync yang dikirim ulang (retry setelah timeout,
 *       flush outbox ganda, atau jaringan yang mengirim dua kali) tidak
 *       pernah menghasilkan perubahan kedua.
 *
 * Bentuk answer_payload per tipe soal:
 *   pg       : {"option_id": 123}
 *   pgk      : {"option_ids": [123, 125, 129]}   <- satu paket jawaban final
 *   boolean  : {"value": true}
 *   matching : {"mapping": {"11": "13", "12": "11"}}  <- id_kiri => id_baris_pasangan
 *
 * client_seq menentukan versi jawaban mana yang paling baru. Sync service
 * menolak payload dengan client_seq lebih kecil daripada yang sudah tersimpan,
 * sehingga flush outbox yang datang tidak berurutan tidak bisa menimpa
 * jawaban yang lebih baru dengan jawaban lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            $table->json('answer_payload')->nullable();

            $table->string('idempotency_key', 80)->unique();

            $table->unsignedBigInteger('client_seq')->default(0);

            // true bila jawaban diterima setelah deadline_at tetapi masih
            // di dalam grace period offline. Ditandai agar admin bisa
            // meninjau apakah nilai perlu dikoreksi.
            $table->boolean('late_sync_flag')->default(false);

            $table->timestamps();

            $table->unique(['attempt_id', 'question_id']);
            $table->index(['attempt_id', 'question_id']);
            $table->index('question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answers');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pasangan untuk soal tipe matching (menjodohkan).
 *
 * Satu baris = satu pasangan benar: left_text harus dipasangkan dengan
 * right_text pada baris yang sama.
 *
 * Di layar siswa, kolom kiri menampilkan left_text dan kolom kanan menampilkan
 * seluruh right_text yang diacak. Jawaban siswa disimpan sebagai peta
 * { id_kiri: id_baris_yang_dipilih } sehingga penilaian cukup membandingkan
 * apakah setiap id_kiri dipetakan ke dirinya sendiri.
 *
 * Penilaian bersifat all-or-nothing: benar hanya bila seluruh pasangan tepat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matching_pairs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            $table->text('left_text');
            $table->text('right_text');
            $table->string('left_media_url')->nullable();
            $table->string('right_media_url')->nullable();

            $table->unsignedInteger('order')->default(0);

            $table->timestamps();

            $table->index(['question_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matching_pairs');
    }
};

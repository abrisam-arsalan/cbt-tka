<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Opsi jawaban untuk tipe pg dan pgk.
 *
 *  - pg : tepat satu baris dengan is_correct = true.
 *  - pgk: dua baris atau lebih dengan is_correct = true. Seluruh himpunan
 *         opsi benar ini adalah "kunci" dan dinilai sebagai satu paket.
 *
 * Tipe boolean dan matching tidak memakai tabel ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('question_id')->constrained()->cascadeOnDelete();

            // "A", "B", "C", ... — bisa null bila soal diimpor tanpa label.
            $table->string('label', 8)->nullable();
            $table->text('option_text');
            $table->string('media_url')->nullable();

            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('order')->default(0);

            $table->timestamps();

            $table->index(['question_id', 'order']);
            $table->index(['question_id', 'is_correct']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('options');
    }
};

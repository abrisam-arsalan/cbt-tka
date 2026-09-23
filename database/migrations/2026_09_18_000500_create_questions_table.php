<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();

            // pg | pgk | boolean | matching (lihat App\Enums\QuestionType)
            $table->string('type', 20)->index();

            // Teks pengantar / bacaan / gambar konteks sebelum pertanyaan.
            $table->text('stimulus')->nullable();
            $table->text('question_text');
            $table->string('media_url')->nullable();

            // "order" adalah kata tercadang di MySQL, tapi grammar Laravel
            // selalu membungkus identifier dengan backtick sehingga aman.
            // Query yang menyentuh kolom ini wajib ditulis qualified
            // (contoh: questions.order) agar tidak ambigu saat join.
            $table->unsignedInteger('order')->default(0);

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Urutan tampil soal pada halaman ujian.
            $table->index(['exam_id', 'is_active', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};

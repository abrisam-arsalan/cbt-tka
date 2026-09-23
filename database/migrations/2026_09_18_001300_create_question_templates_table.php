<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Template soal untuk impor massal.
 *
 * Setiap baris mewakili satu format template (per tipe soal) yang bisa
 * diunduh admin, diisi, lalu diunggah kembali. "sample_rows" menyimpan
 * contoh baris agar file template yang diunduh sudah terisi contoh dan
 * admin tidak perlu menebak format kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_templates', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            // pg | pgk | boolean | matching
            $table->string('question_type', 20)->index();

            // Path ke disk "public" bila admin mengunggah file template sendiri.
            $table->string('file_path')->nullable();

            // Daftar kolom + contoh baris yang dipakai generator template.
            $table->json('columns')->nullable();
            $table->json('sample_rows')->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_builtin')->default(false);

            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['question_type', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_templates');
    }
};

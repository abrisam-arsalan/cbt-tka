<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kelas / rombongan belajar.
 *
 * Dibuat sebelum users karena users.class_id merujuk ke tabel ini.
 * Sengaja tidak punya kolom wali kelas agar tidak terbentuk foreign key
 * melingkar (classes -> users -> classes) yang menyulitkan seeding dan rollback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->string('grade', 16)->nullable();
            $table->string('academic_year', 16)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['name', 'academic_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};

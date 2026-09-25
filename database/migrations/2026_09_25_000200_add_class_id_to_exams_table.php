<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            // Ujian yang dibatasi kelas hanya bisa diakses siswa di kelas itu.
            // Null berarti tidak dibatasi (semua peserta terdaftar boleh masuk).
            $table->foreignId('class_id')->nullable()
                ->after('description')
                ->constrained('classes')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('class_id');
        });
    }
};

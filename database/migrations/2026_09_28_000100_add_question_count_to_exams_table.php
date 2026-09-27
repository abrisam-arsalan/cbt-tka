<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Jumlah soal yang ditampilkan saat ujian.
     *
     * mis. bank berisi 60 soal, question_count = 30 => tiap siswa mendapat
     * 30 soal. Subset dipilih deterministik dari shuffle_seed attempt sehingga
     * layar ujian, penilaian, dan pembahasan selalu konsisten walau di-refresh.
     * NULL = semua soal aktif ditampilkan.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->unsignedSmallInteger('question_count')->nullable()->after('duration_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('question_count');
        });
    }
};

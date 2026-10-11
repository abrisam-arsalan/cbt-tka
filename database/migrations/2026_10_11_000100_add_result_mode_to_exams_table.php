<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mode hasil yang dilihat siswa setelah mengumpulkan:
     *  full_review -> nilai + pembahasan semua soal (perilaku lama, default)
     *  score_only  -> hanya angka nilai; daftar jawaban disembunyikan.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('result_mode', 20)->default('full_review')->after('require_fullscreen');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('result_mode');
        });
    }
};

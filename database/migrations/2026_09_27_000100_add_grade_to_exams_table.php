<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Target ujian per JENJANG (7/8/9 untuk SMP).
     *
     * Aturan akses: class_id terisi = khusus rombel itu;
     * class_id null + grade terisi = semua rombel di jenjang itu;
     * keduanya null = semua siswa.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('grade', 8)->nullable()->after('class_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('grade');
        });
    }
};

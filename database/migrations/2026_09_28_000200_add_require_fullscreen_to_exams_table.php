<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bila aktif, siswa wajib berada dalam layar penuh (fullscreen) selama
     * mengerjakan. Keluar fullscreen (termasuk membuka floating window /
     * split-screen Android, atau menekan Esc) memicu peringatan anti-cheat
     * dan layar ujian dikunci sampai siswa kembali ke fullscreen.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->boolean('require_fullscreen')->default(false)->after('anti_cheat_action');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn('require_fullscreen');
        });
    }
};

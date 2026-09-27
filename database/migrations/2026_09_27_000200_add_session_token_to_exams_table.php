<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token SESI per ujian (satu untuk semua peserta, berganti otomatis tiap
     * 30 menit mengikuti jam dinding :00 / :30).
     *
     * - session_token_hash   : SHA-256 token aktif, untuk verifikasi join.
     * - session_token_cipher : plaintext terenkripsi, untuk ditampilkan di
     *                          halaman monitoring.
     * - session_window       : id jendela 30-menit (epoch/1800) saat token
     *                          dibuat — dipakai untuk rotasi lazy.
     */
    public function up(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->string('session_token_hash', 64)->nullable()->after('grade');
            $table->text('session_token_cipher')->nullable()->after('session_token_hash');
            $table->unsignedBigInteger('session_window')->nullable()->after('session_token_cipher');
            // Grace: token window sebelumnya masih diterima 30 menit setelah
            // berganti, agar siswa yang sedang mengetik tidak gagal.
            $table->string('previous_token_hash', 64)->nullable()->after('session_window');
            $table->unsignedBigInteger('previous_token_window')->nullable()->after('previous_token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            $table->dropColumn([
                'session_token_hash', 'session_token_cipher', 'session_window',
                'previous_token_hash', 'previous_token_window',
            ]);
        });
    }
};

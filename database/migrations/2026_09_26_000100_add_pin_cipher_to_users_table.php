<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PIN login siswa (6 digit angka) disimpan dua kali seperti token ujian:
     *  - password    : hash bcrypt, dipakai untuk login.
     *  - pin_cipher  : plaintext terenkripsi APP_KEY, HANYA dipakai agar
     *                  kartu ujian bisa dicetak ulang tanpa reset PIN.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('pin_cipher')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pin_cipher');
        });
    }
};

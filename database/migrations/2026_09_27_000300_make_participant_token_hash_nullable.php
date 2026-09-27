<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token tidak lagi per peserta (kini token sesi per ujian), tapi kolom
     * token_hash masih ber-index UNIQUE (exam_id, token_hash). Peserta baru
     * dibuat tanpa token => nilai '' kedua menabrak unique constraint.
     * NULL boleh berulang pada unique index, jadi kolom dibuat nullable
     * dan baris lama bernilai '' dinormalisasi ke NULL.
     */
    public function up(): void
    {
        DB::table('exam_participants')->where('token_hash', '')->update(['token_hash' => null]);

        Schema::table('exam_participants', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('exam_participants', function (Blueprint $table) {
            $table->string('token_hash', 64)->nullable(false)->change();
        });
    }
};

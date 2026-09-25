<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mengubah `questions` menjadi tabel ganda-guna:
 *   - soal BANK  : exam_id NULL, batch_id terisi, class_id terisi
 *   - soal UJIAN : exam_id terisi (salinan dari bank saat ujian dibuat)
 *
 * Karena MySQL dan SQLite sama-sama perlu mengubah nullability kolom
 * ber-FK, tabel dibangun ulang secara manual agar deterministik di kedua
 * mesin (Laravel `->change()` tidak selalu mempertahankan FK pada SQLite).
 * Data lama tetap disalin; pada deployment ini data ujian lama akan diwipe
 * terpisah, sehingga migrasi ini tetap aman dijalankan sebelum wipe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('questions', 'batch_id')) {
            return;
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $this->rebuildSqlite();

            return;
        }

        // MySQL / MariaDB: ubah langsung.
        Schema::table('questions', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_id')->nullable()->change();
            $table->foreignId('batch_id')->nullable()->after('exam_id')
                ->constrained('question_batches')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->after('batch_id')
                ->constrained('classes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Tidak didukung rollback parsial; gunakan migrate:fresh pada dev.
    }

    private function rebuildSqlite(): void
    {
        // Ambil seluruh baris lama, lalu bangun ulang dengan skema baru.
        $existing = DB::table('questions')->get()->map(fn ($r) => (array) $r)->all();

        // options & matching_pairs ber-FK cascade ke questions; matikan FK agar
        // drop/rename tidak menghapus anak-anaknya, lalu pulihkan.
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::drop('questions');

        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('exam_id')->nullable()->index();
            $table->unsignedBigInteger('batch_id')->nullable()->index();
            $table->unsignedBigInteger('class_id')->nullable()->index();
            $table->string('type', 20)->index();
            $table->text('stimulus')->nullable();
            $table->text('question_text');
            $table->string('media_url')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['exam_id', 'is_active', 'order']);
        });

        // Bangun kembali FK.
        Schema::table('questions', function (Blueprint $table) {
            $table->foreign('exam_id')->references('id')->on('exams')->cascadeOnDelete();
            $table->foreign('batch_id')->references('id')->on('question_batches')->cascadeOnDelete();
            $table->foreign('class_id')->references('id')->on('classes')->nullOnDelete();
        });

        if ($existing !== []) {
            foreach ($existing as $row) {
                $row['exam_id'] = $row['exam_id'] ?? null;
                $row['batch_id'] = null;
                $row['class_id'] = null;
                DB::table('questions')->insert($row);
            }
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }
};

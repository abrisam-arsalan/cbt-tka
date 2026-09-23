<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attempt ujian — satu baris per siswa per ujian.
 *
 * Tiga penanda waktu yang berbeda peran dan tidak boleh tertukar:
 *
 *   deadline_at : batas akhir waktu pengerjaan siswa
 *                 (started_at + duration_minutes + extended_minutes).
 *                 Setelah lewat, siswa tidak bisa menjawab lagi di UI,
 *                 tetapi sinkronisasi offline masih diterima.
 *
 *   expires_at  : deadline_at + offline_grace_minutes ujian.
 *                 Setelah waktu ini, server MENOLAK semua jawaban baru.
 *                 Inilah pagar terakhir untuk outbox offline.
 *
 *   expired_at  : cap waktu saat scheduler menandai attempt kedaluwarsa.
 *                 Diisi sekali, dipakai untuk audit dan idempotensi scheduler.
 *
 * unique(exam_id, user_id) menjamin tidak ada dua attempt untuk siswa yang
 * sama pada satu ujian, sehingga "lanjutkan attempt" selalu ambigu-free.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('exam_participant_id')->nullable()
                ->constrained('exam_participants')
                ->nullOnDelete();

            // in_progress | locked | expired | submitted (App\Enums\AttemptStatus)
            $table->string('status', 20)->default('in_progress')->index();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('deadline_at')->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->string('lock_reason', 40)->nullable();
            // manual | time_up | exam_closed | anti_cheat_limit | admin_force
            $table->string('submit_reason', 40)->nullable();

            // Perpanjangan waktu yang diberikan admin dari halaman monitoring.
            $table->unsignedSmallInteger('extended_minutes')->default(0);

            // Seed acak per attempt agar urutan soal stabil walau halaman
            // di-refresh. Tanpa ini, shuffle berubah setiap render dan
            // navigasi nomor soal siswa jadi kacau.
            $table->unsignedBigInteger('shuffle_seed')->nullable();

            $table->unsignedSmallInteger('warnings_count')->default(0);
            $table->boolean('anti_cheat_enforced')->default(false);

            // Hasil penilaian, diisi ScoringService saat submit.
            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedSmallInteger('wrong_count')->default(0);
            $table->unsignedSmallInteger('unanswered_count')->default(0);
            $table->decimal('score', 6, 2)->nullable();

            $table->string('started_ip', 45)->nullable();
            $table->string('submitted_ip', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamps();

            $table->unique(['exam_id', 'user_id']);

            // Dipakai scheduler auto-submit: cari attempt aktif yang lewat deadline.
            $table->index(['status', 'deadline_at']);
            // Dipakai dashboard monitoring admin per ujian.
            $table->index(['exam_id', 'status']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempts');
    }
};

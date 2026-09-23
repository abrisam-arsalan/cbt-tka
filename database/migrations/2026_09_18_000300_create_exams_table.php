<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();

            $table->unsignedSmallInteger('duration_minutes');

            // Jendela pelaksanaan ujian. Nullable agar admin bisa membuat ujian
            // draf tanpa jadwal lalu melengkapinya kemudian.
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();

            $table->string('status', 20)->default('draft')->index();

            // Diisi saat admin menjeda ujian, dikosongkan saat dilanjutkan.
            // Diperlukan agar durasi jeda bisa ditambahkan kembali ke
            // deadline setiap attempt — tanpa ini, jeda admin memakan waktu
            // pengerjaan siswa dan merugikan mereka.
            $table->timestamp('paused_at')->nullable();

            $table->boolean('anti_cheat_enabled')->default(false);
            $table->unsignedTinyInteger('anti_cheat_max_warnings')->default(3);
            $table->string('anti_cheat_action', 40)->default('log_only');

            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);

            // Berapa lama jawaban dari outbox offline masih diterima
            // setelah deadline siswa tercapai.
            $table->unsignedSmallInteger('offline_grace_minutes')->default(10);

            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // Dipakai scheduler dan daftar ujian siswa.
            $table->index(['status', 'start_at', 'end_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};

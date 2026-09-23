<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();

            // username adalah identitas login siswa (biasanya NISN).
            // Dipisah dari nisn karena sebagian sekolah memakai nomor induk lain.
            $table->string('username', 64)->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('password');

            $table->string('role', 20)->default('siswa')->index();

            $table->foreignId('class_id')->nullable()
                ->constrained('classes')
                ->nullOnDelete();

            $table->string('nisn', 32)->nullable()->unique();
            $table->string('phone', 32)->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();

            $table->rememberToken();
            $table->timestamps();

            // Soft delete: riwayat attempt, jawaban, dan nilai siswa harus tetap
            // utuh untuk audit walaupun akunnya sudah tidak dipakai.
            $table->softDeletes();

            $table->index(['role', 'is_active']);
            $table->index(['class_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};

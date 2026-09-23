<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log tindakan admin.
 *
 * Append-only: hanya created_at. Baris tidak pernah diubah atau dihapus dari UI
 * karena fungsinya sebagai jejak akuntabilitas.
 *
 * "meta" menyimpan pasangan before/after untuk perubahan data sehingga admin
 * bisa melihat apa persisnya yang berubah, bukan hanya bahwa sesuatu berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Nullable: aksi bisa terjadi lewat scheduler tanpa user login.
            $table->foreignId('user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('action', 100)->index();

            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();

            $table->string('description')->nullable();
            $table->json('meta')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

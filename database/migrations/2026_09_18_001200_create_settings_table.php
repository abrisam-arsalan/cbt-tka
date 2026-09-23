<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan sistem yang bisa diubah admin lewat UI.
 *
 * Disimpan sebagai pasangan key/value agar menambah pengaturan baru tidak
 * memerlukan migration. Kolom "type" memberi tahu SettingService cara
 * meng-cast "value" (string, int, bool, json).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('key', 120)->unique();
            $table->text('value')->nullable();

            // string | int | bool | json
            $table->string('type', 20)->default('string');

            // general | exam | anti_cheat | card | appearance
            $table->string('group', 40)->default('general')->index();

            $table->string('label')->nullable();
            $table->text('description')->nullable();

            // true bila nilai boleh dikirim ke browser siswa.
            $table->boolean('is_public')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('class_id')->nullable()
                ->constrained('classes')
                ->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['class_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_batches');
    }
};

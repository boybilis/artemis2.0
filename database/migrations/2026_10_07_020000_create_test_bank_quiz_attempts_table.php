<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_bank_quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_bank_quiz_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('score');
            $table->unsignedInteger('total');
            $table->decimal('points_earned', 10, 2)->default(0);
            $table->decimal('points_possible', 10, 2)->default(0);
            $table->boolean('passed')->default(false);
            $table->json('review_data')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'test_bank_id', 'created_at'], 'tb_attempt_user_bank_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_bank_quiz_attempts');
    }
};

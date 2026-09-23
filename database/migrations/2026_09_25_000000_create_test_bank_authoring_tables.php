<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('test_bank_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_bank_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->json('options');
            $table->unsignedTinyInteger('correct_answer')->comment('Zero-based option index');
            $table->text('rationale')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['test_bank_id', 'subject_id', 'status']);
        });

        Schema::create('test_bank_quizzes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_bank_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('item_count');
            $table->json('subject_ids');
            $table->boolean('randomize_questions')->default(true);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('test_bank_quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_bank_quiz_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_bank_question_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['test_bank_quiz_id', 'test_bank_question_id'], 'tb_quiz_question_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_bank_quiz_questions');
        Schema::dropIfExists('test_bank_quizzes');
        Schema::dropIfExists('test_bank_questions');
    }
};

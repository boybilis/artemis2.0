<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Topics
        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Lessons
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->string('video_url');
            $table->text('notes')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 3. Quiz Questions (nullable topic_id means Final Exam question)
        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->nullable()->constrained()->onDelete('cascade');
            $table->text('question');
            $table->text('options'); // JSON array of options stored as text or JSON
            $table->integer('answer'); // Index of the correct option
            $table->timestamps();
        });

        // 4. User Progress (completed topics)
        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('topic_id')->constrained()->onDelete('cascade');
            $table->timestamp('completed_at')->useCurrent();
            $table->timestamps();
            
            $table->unique(['user_id', 'topic_id']);
        });

        // 5. Quiz Attempts
        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('topic_id')->nullable()->constrained()->onDelete('cascade'); // NULL means Final Exam
            $table->integer('score');
            $table->integer('total');
            $table->boolean('passed');
            $table->timestamps();
        });

        // 6. Vouchers
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->decimal('price', 8, 2)->default(299.00);
            $table->boolean('used')->default(false);
            $table->foreignId('used_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        // 7. Certificates
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->timestamp('issued_at')->useCurrent();
            $table->timestamps();
        });

        // 8. Announcements
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
        });

        // 9. Audit Logs
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('action');
            $table->text('description');
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('certificates');
        Schema::dropIfExists('vouchers');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('user_progress');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('topics');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['course_id', 'status']);
        });

        Schema::create('course_batch_instructors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['course_batch_id', 'user_id']);
        });

        Schema::table('course_enrollments', fn (Blueprint $table) => $table->foreignId('batch_id')->nullable()->after('course_id')->constrained('course_batches')->nullOnDelete());
        Schema::table('vouchers', fn (Blueprint $table) => $table->foreignId('batch_id')->nullable()->after('course_id')->constrained('course_batches')->nullOnDelete());
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->foreignId('batch_id')->nullable()->after('course_id')->constrained('course_batches')->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropConstrainedForeignId('batch_id'));
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropConstrainedForeignId('batch_id'));
        Schema::table('course_enrollments', fn (Blueprint $table) => $table->dropConstrainedForeignId('batch_id'));
        Schema::dropIfExists('course_batch_instructors');
        Schema::dropIfExists('course_batches');
    }
};

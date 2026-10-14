<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('question_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('attempt_type');
            $table->unsignedBigInteger('attempt_id');
            $table->unsignedInteger('question_index');
            $table->json('question_snapshot');
            $table->text('reason');
            $table->text('reply')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('replied_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('question_flags'); }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subtopics', function (Blueprint $table) {
            $table->string('content_type', 30)->default('subtopic')->after('topic_id');
            $table->text('instructions')->nullable()->after('title');
            $table->unsignedTinyInteger('maximum_attempts')->nullable()->after('instructions');
        });
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->foreignId('subtopic_id')->nullable()->after('topic_id')->constrained()->cascadeOnDelete();
        });
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->foreignId('subtopic_id')->nullable()->after('topic_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropConstrainedForeignId('subtopic_id'));
        Schema::table('quiz_questions', fn (Blueprint $table) => $table->dropConstrainedForeignId('subtopic_id'));
        Schema::table('subtopics', fn (Blueprint $table) => $table->dropColumn(['content_type', 'instructions', 'maximum_attempts']));
    }
};

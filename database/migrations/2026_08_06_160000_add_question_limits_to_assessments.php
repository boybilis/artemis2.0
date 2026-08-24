<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', fn (Blueprint $table) => $table->unsignedSmallInteger('assessment_question_count')->nullable()->after('assessment_time_limit_minutes'));
        Schema::table('subtopics', fn (Blueprint $table) => $table->unsignedSmallInteger('assessment_question_count')->nullable()->after('assessment_time_limit_minutes'));
        Schema::table('courses', fn (Blueprint $table) => $table->unsignedSmallInteger('mock_exam_question_count')->nullable()->after('mock_exam_time_limit_minutes'));
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('mock_exam_question_count'));
        Schema::table('subtopics', fn (Blueprint $table) => $table->dropColumn('assessment_question_count'));
        Schema::table('topics', fn (Blueprint $table) => $table->dropColumn('assessment_question_count'));
    }
};

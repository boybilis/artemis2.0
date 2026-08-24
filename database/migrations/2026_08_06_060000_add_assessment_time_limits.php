<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', fn (Blueprint $table) => $table->unsignedSmallInteger('assessment_time_limit_minutes')->nullable()->after('quiz_passing_percentage'));
        Schema::table('subtopics', fn (Blueprint $table) => $table->unsignedSmallInteger('assessment_time_limit_minutes')->nullable()->after('passing_percentage'));
        Schema::table('courses', fn (Blueprint $table) => $table->unsignedSmallInteger('mock_exam_time_limit_minutes')->nullable()->after('mock_exam_passing_percentage'));
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('mock_exam_time_limit_minutes'));
        Schema::table('subtopics', fn (Blueprint $table) => $table->dropColumn('assessment_time_limit_minutes'));
        Schema::table('topics', fn (Blueprint $table) => $table->dropColumn('assessment_time_limit_minutes'));
    }
};

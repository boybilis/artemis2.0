<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $courseId = DB::table('courses')->insertGetId([
            'title' => 'CSS Tutorial',
            'description' => 'Learn CSS from scratch.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('topics', function (Blueprint $table) use ($courseId) {
            $table->foreignId('course_id')->default($courseId)->constrained()->onDelete('cascade');
        });

        Schema::table('certificates', function (Blueprint $table) use ($courseId) {
            $table->foreignId('course_id')->default($courseId)->constrained()->onDelete('cascade');
        });

        Schema::table('user_progress', function (Blueprint $table) use ($courseId) {
            $table->foreignId('course_id')->default($courseId)->constrained()->onDelete('cascade');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) use ($courseId) {
            $table->foreignId('course_id')->default($courseId)->constrained()->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });

        Schema::table('user_progress', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });

        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });

        Schema::table('topics', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });
    }
};

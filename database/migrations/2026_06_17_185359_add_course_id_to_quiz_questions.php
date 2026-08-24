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
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->constrained()->onDelete('cascade');
        });

        // Populate existing quiz_questions course_id
        $topics = \App\Models\Topic::all();
        foreach ($topics as $topic) {
            \Illuminate\Support\Facades\DB::table('quiz_questions')
                ->where('topic_id', $topic->id)
                ->update(['course_id' => $topic->course_id]);
        }

        // Set default for any remaining (e.g. final exam questions)
        $defaultCourse = \App\Models\Course::first();
        if ($defaultCourse) {
            \Illuminate\Support\Facades\DB::table('quiz_questions')
                ->whereNull('course_id')
                ->update(['course_id' => $defaultCourse->id]);
        }

        // Make it non-nullable if desired, though we'll leave it as nullable or just leave as is since we populated it.
        // Actually, let's keep it nullable if there are no courses? No, courses exist.
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropColumn('course_id');
        });
    }
};

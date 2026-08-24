<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->string('question_type', 20)->default('quiz')->after('course_id');
            $table->string('response_type', 20)->default('single')->after('question_type');
            $table->json('correct_answers')->nullable()->after('answer');
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->string('assessment_type', 20)->default('quiz')->after('topic_id');
        });

        DB::table('quiz_questions')->orderBy('id')->each(function ($question) {
            DB::table('quiz_questions')->where('id', $question->id)->update([
                'question_type' => $question->topic_id === null ? 'final' : 'quiz',
                'response_type' => 'single',
                'correct_answers' => json_encode([(int) $question->answer]),
            ]);
        });

        DB::table('quiz_attempts')->whereNull('topic_id')->update(['assessment_type' => 'final']);
    }

    public function down(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn(['question_type', 'response_type', 'correct_answers']);
        });

        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->dropColumn('assessment_type');
        });
    }
};

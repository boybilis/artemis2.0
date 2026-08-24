<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedTinyInteger('mock_exam_maximum_attempts')->nullable()->default(2);
            $table->unsignedTinyInteger('mock_exam_passing_percentage')->default(80);
            $table->unsignedSmallInteger('mock_exam_time_limit_minutes')->nullable();
        });
        DB::table('courses')->orderBy('id')->each(function ($course) {
            $batch = DB::table('course_batches')->where('course_id', $course->id)->orderBy('id')->first();
            if ($batch) DB::table('courses')->where('id', $course->id)->update([
                'mock_exam_maximum_attempts'=>$batch->mock_exam_maximum_attempts,
                'mock_exam_passing_percentage'=>$batch->mock_exam_passing_percentage,
                'mock_exam_time_limit_minutes'=>$batch->mock_exam_time_limit_minutes,
            ]);
        });
        Schema::table('course_batches', fn (Blueprint $table) => $table->dropColumn(['mock_exam_maximum_attempts','mock_exam_passing_percentage','mock_exam_time_limit_minutes']));
    }

    public function down(): void {}
};

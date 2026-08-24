<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->unsignedTinyInteger('mock_exam_maximum_attempts')->nullable()->default(2)->after('capacity');
            $table->unsignedTinyInteger('mock_exam_passing_percentage')->default(80)->after('mock_exam_maximum_attempts');
            $table->unsignedSmallInteger('mock_exam_time_limit_minutes')->nullable()->after('mock_exam_passing_percentage');
        });
        if (Schema::hasColumn('courses', 'mock_exam_maximum_attempts')) {
            DB::table('course_batches')->orderBy('id')->each(function ($batch) {
                $course = DB::table('courses')->where('id', $batch->course_id)->first();
                if ($course) DB::table('course_batches')->where('id', $batch->id)->update([
                    'mock_exam_maximum_attempts'=>$course->mock_exam_maximum_attempts,
                    'mock_exam_passing_percentage'=>$course->mock_exam_passing_percentage,
                    'mock_exam_time_limit_minutes'=>$course->mock_exam_time_limit_minutes,
                ]);
            });
            Schema::table('courses', fn (Blueprint $table) => $table->dropColumn(['mock_exam_maximum_attempts','mock_exam_passing_percentage','mock_exam_time_limit_minutes']));
        }
    }

    public function down(): void {}
};

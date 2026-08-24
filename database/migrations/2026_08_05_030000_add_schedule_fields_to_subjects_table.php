<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('subject_code', 50)->nullable()->after('course_id');
            $table->date('start_date')->nullable()->after('description');
            $table->date('end_date')->nullable()->after('start_date');
            $table->string('schedule_day', 30)->nullable()->after('end_date');
            $table->time('start_time')->nullable()->after('schedule_day');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('modality', 30)->nullable()->after('end_time');
            $table->unique(['course_id', 'subject_code']);
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropUnique(['course_id', 'subject_code']);
            $table->dropColumn(['subject_code', 'start_date', 'end_date', 'schedule_day', 'start_time', 'end_time', 'modality']);
        });
    }
};

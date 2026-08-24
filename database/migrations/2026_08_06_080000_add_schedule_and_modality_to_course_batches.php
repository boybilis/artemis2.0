<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->string('schedule_day', 30)->nullable()->after('ends_at');
            $table->time('start_time')->nullable()->after('schedule_day');
            $table->time('end_time')->nullable()->after('start_time');
            $table->string('modality', 30)->nullable()->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('course_batches', fn (Blueprint $table) => $table->dropColumn(['schedule_day', 'start_time', 'end_time', 'modality']));
    }
};

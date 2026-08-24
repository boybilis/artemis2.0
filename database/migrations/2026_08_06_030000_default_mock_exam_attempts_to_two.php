<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('courses')->whereNull('mock_exam_maximum_attempts')
            ->update(['mock_exam_maximum_attempts' => 2]);

        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedTinyInteger('mock_exam_maximum_attempts')->nullable()->default(2)->change();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->unsignedTinyInteger('mock_exam_maximum_attempts')->nullable()->default(null)->change();
        });
    }
};

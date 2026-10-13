<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->string('course_type', 30)->default('comprehensive');
        });
        DB::table('course_batches')->where('includes_intensive_final_coaching', true)->update(['course_type' => 'complete']);
        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('is_mentoring')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('subjects', fn (Blueprint $table) => $table->dropColumn('is_mentoring'));
        Schema::table('course_batches', fn (Blueprint $table) => $table->dropColumn('course_type'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->boolean('is_intensive_final_coaching')->default(false)->after('description');
            $table->index(['course_id', 'is_intensive_final_coaching'], 'subjects_course_ifc_index');
        });
        Schema::table('course_batches', function (Blueprint $table) {
            $table->boolean('includes_intensive_final_coaching')->default(false)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropIndex('subjects_course_ifc_index');
            $table->dropColumn('is_intensive_final_coaching');
        });
        Schema::table('course_batches', function (Blueprint $table) {
            $table->dropColumn('includes_intensive_final_coaching');
        });
    }
};

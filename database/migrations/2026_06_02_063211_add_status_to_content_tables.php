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
        Schema::table('topics', function (Blueprint $table) {
            $table->string('status')->default('approved');
        });
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('status')->default('approved');
        });
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->string('status')->default('approved');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn('status');
        });
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};

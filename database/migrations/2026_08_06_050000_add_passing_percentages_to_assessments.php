<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', fn (Blueprint $table) => $table->unsignedTinyInteger('quiz_passing_percentage')->default(80)->after('status'));
        Schema::table('subtopics', fn (Blueprint $table) => $table->unsignedTinyInteger('passing_percentage')->default(80)->after('maximum_attempts'));
    }

    public function down(): void
    {
        Schema::table('subtopics', fn (Blueprint $table) => $table->dropColumn('passing_percentage'));
        Schema::table('topics', fn (Blueprint $table) => $table->dropColumn('quiz_passing_percentage'));
    }
};

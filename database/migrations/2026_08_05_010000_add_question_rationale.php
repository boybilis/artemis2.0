<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_questions', fn (Blueprint $table) => $table->text('rationale')->nullable()->after('question'));
    }

    public function down(): void
    {
        Schema::table('quiz_questions', fn (Blueprint $table) => $table->dropColumn('rationale'));
    }
};

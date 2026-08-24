<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->json('review_data')->nullable()->after('passed');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropColumn('review_data'));
    }
};

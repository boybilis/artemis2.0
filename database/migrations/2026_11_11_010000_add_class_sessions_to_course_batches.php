<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('course_batches', fn (Blueprint $table) => $table->json('class_sessions')->nullable());
    }

    public function down(): void
    {
        Schema::table('course_batches', fn (Blueprint $table) => $table->dropColumn('class_sessions'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->foreignId('subject_id')->nullable()->after('course_id')->constrained()->nullOnDelete();
            $table->index(['course_id', 'subject_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::table('topics', function (Blueprint $table) {
            $table->dropIndex(['course_id', 'subject_id', 'sort_order']);
            $table->dropConstrainedForeignId('subject_id');
        });
    }
};

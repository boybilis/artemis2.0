<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_bank_quizzes', function (Blueprint $table) {
            $table->string('quiz_type', 20)->default('premade')->after('test_bank_id');
            $table->foreignId('owner_user_id')->nullable()->after('quiz_type')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('time_limit_minutes')->nullable()->after('item_count');
            $table->index(['test_bank_id', 'quiz_type', 'owner_user_id'], 'tb_quiz_catalog_type_owner');
        });
    }

    public function down(): void
    {
        Schema::table('test_bank_quizzes', function (Blueprint $table) {
            $table->dropIndex('tb_quiz_catalog_type_owner');
            $table->dropConstrainedForeignId('owner_user_id');
            $table->dropColumn(['quiz_type', 'time_limit_minutes']);
        });
    }
};

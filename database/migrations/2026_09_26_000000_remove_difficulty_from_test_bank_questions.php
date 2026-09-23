<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('test_bank_questions') && Schema::hasColumn('test_bank_questions', 'difficulty')) {
            Schema::table('test_bank_questions', function (Blueprint $table) {
                $table->dropColumn('difficulty');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('test_bank_questions') && !Schema::hasColumn('test_bank_questions', 'difficulty')) {
            Schema::table('test_bank_questions', function (Blueprint $table) {
                $table->enum('difficulty', ['easy', 'average', 'difficult'])->default('average')->after('rationale');
            });
        }
    }
};

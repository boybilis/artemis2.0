<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('test_bank_questions')) {
            return;
        }

        Schema::table('test_bank_questions', function (Blueprint $table) {
            if (!Schema::hasColumn('test_bank_questions', 'image_path')) {
                $table->string('image_path')->nullable()->after('rationale');
            }
            if (!Schema::hasColumn('test_bank_questions', 'image_filename')) {
                $table->string('image_filename')->nullable()->after('image_path');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('test_bank_questions')) {
            return;
        }

        Schema::table('test_bank_questions', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['image_path', 'image_filename'],
                fn ($column) => Schema::hasColumn('test_bank_questions', $column)
            ));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema, DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::table('test_bank_quizzes', function (Blueprint $table) {
            $table->string('test_type', 20)->nullable();
        });
        DB::table('test_bank_quizzes')->where('quiz_type', 'premade')->whereIn(DB::raw('LOWER(TRIM(title))'), ['simulation test 1', 'simulation test 2'])->update(['test_type' => 'simulation']);
        DB::table('test_bank_quizzes')->where('quiz_type', 'premade')->whereNull('test_type')->where('item_count', 10)->update(['test_type' => 'warm_up']);
        DB::table('test_bank_quizzes')->where('quiz_type', 'premade')->whereNull('test_type')->where('item_count', 20)->update(['test_type' => 'mastery']);
    }
    public function down(): void
    {
        Schema::table('test_bank_quizzes', fn (Blueprint $table) => $table->dropColumn('test_type'));
    }
};

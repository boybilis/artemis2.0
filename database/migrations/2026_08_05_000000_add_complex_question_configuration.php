<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_questions', function (Blueprint $table) {
            $table->json('response_config')->nullable()->after('correct_answers');
            $table->decimal('maximum_points', 8, 2)->default(1)->after('response_config');
            $table->string('scoring_method', 30)->default('all_or_nothing')->after('maximum_points');
        });
        Schema::table('quiz_attempts', function (Blueprint $table) {
            $table->decimal('points_earned', 10, 2)->nullable()->after('total');
            $table->decimal('points_possible', 10, 2)->nullable()->after('points_earned');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempts', fn (Blueprint $table) => $table->dropColumn(['points_earned', 'points_possible']));
        Schema::table('quiz_questions', fn (Blueprint $table) => $table->dropColumn(['response_config', 'maximum_points', 'scoring_method']));
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('question_flags', function (Blueprint $table) {
            $table->timestamp('staff_read_at')->nullable()->index();
            $table->timestamp('learner_read_at')->nullable();
        });
    }
    public function down(): void {
        Schema::table('question_flags', function (Blueprint $table) {
            $table->dropIndex(['staff_read_at']);
            $table->dropColumn(['staff_read_at','learner_read_at']);
        });
    }
};

<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('test_bank_quiz_attempts', fn(Blueprint $table)=>$table->string('attempt_token',64)->nullable()->unique()); }
    public function down(): void { Schema::table('test_bank_quiz_attempts',function(Blueprint $table){$table->dropUnique(['attempt_token']);$table->dropColumn('attempt_token');}); }
};

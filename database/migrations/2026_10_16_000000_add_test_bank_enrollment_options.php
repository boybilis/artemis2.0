<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('test_banks', fn(Blueprint $table) => $table->json('enrollment_options')->nullable());
        Schema::table('test_bank_enrollments', fn(Blueprint $table) => $table->json('enrollment_option')->nullable());
        Schema::table('payment_transactions', fn(Blueprint $table) => $table->json('enrollment_option')->nullable());
    }
    public function down(): void {
        Schema::table('payment_transactions', fn(Blueprint $table) => $table->dropColumn('enrollment_option'));
        Schema::table('test_bank_enrollments', fn(Blueprint $table) => $table->dropColumn('enrollment_option'));
        Schema::table('test_banks', fn(Blueprint $table) => $table->dropColumn('enrollment_options'));
    }
};

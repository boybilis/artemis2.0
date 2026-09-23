<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('batch_id')->nullable()->change();
            $table->foreignId('test_bank_id')->nullable()->after('review_package_id')->constrained()->nullOnDelete();
            $table->index(['user_id', 'test_bank_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'test_bank_id', 'status']);
            $table->dropConstrainedForeignId('test_bank_id');
            $table->foreignId('batch_id')->nullable(false)->change();
        });
    }
};

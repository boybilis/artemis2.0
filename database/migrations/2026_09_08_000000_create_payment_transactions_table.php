<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('course_batches')->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('PHP');
            $table->string('provider', 30)->default('paymongo');
            $table->string('provider_checkout_id')->nullable()->unique();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->string('status', 40)->default('pending_payment');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'batch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};

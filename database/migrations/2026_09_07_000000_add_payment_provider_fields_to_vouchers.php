<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->string('payment_provider', 30)->nullable()->after('status');
            $table->string('provider_checkout_id')->nullable()->unique()->after('payment_provider');
            $table->string('provider_payment_id')->nullable()->unique()->after('provider_checkout_id');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropUnique(['provider_checkout_id']);
            $table->dropUnique(['provider_payment_id']);
            $table->dropColumn(['payment_provider', 'provider_checkout_id', 'provider_payment_id']);
        });
    }
};

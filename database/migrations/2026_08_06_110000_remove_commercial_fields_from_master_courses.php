<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn(['price', 'usd_price', 'access_days', 'available_from', 'available_until']));
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('price', 8, 2)->default(0);
            $table->decimal('usd_price', 10, 2)->nullable();
            $table->unsignedInteger('access_days')->default(30);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
        });
    }
};

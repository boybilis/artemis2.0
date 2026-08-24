<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->string('country_code', 10)->default('PH')->after('phone'));
        Schema::table('courses', fn (Blueprint $table) => $table->decimal('usd_price', 10, 2)->default(5.99)->after('price'));
    }

    public function down(): void
    {
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn('usd_price'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('country_code'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('test_banks', fn (Blueprint $table) => $table->boolean('simulation_unlocked_by_admin')->default(false));
    }
    public function down(): void
    {
        Schema::table('test_banks', fn (Blueprint $table) => $table->dropColumn('simulation_unlocked_by_admin'));
    }
};

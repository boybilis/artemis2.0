<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', fn (Blueprint $table) => $table->string('status', 20)->default('approved')->after('sort_order'));
    }

    public function down(): void
    {
        Schema::table('subjects', fn (Blueprint $table) => $table->dropColumn('status'));
    }
};

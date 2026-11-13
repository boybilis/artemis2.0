<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_credentials', function (Blueprint $table) {
            $table->string('provider', 30)->primary();
            $table->text('encrypted_credentials');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_credentials');
    }
};

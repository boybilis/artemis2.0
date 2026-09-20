<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('batch_zoom_sessions', function (Blueprint $table) {
            $table->string('recording_url', 1000)->nullable()->after('zoom_url');
        });
    }

    public function down(): void
    {
        Schema::table('batch_zoom_sessions', function (Blueprint $table) {
            $table->dropColumn('recording_url');
        });
    }
};

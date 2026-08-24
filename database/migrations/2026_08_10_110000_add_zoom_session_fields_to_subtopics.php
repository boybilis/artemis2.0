<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subtopics', function (Blueprint $table) {
            $table->string('zoom_url', 1000)->nullable()->after('instructions');
            $table->text('zoom_description')->nullable()->after('zoom_url');
            $table->dateTime('zoom_starts_at')->nullable()->after('zoom_description');
            $table->dateTime('zoom_ends_at')->nullable()->after('zoom_starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('subtopics', fn (Blueprint $table) => $table->dropColumn(['zoom_url','zoom_description','zoom_starts_at','zoom_ends_at']));
    }
};

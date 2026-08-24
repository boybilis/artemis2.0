<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subtopics', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('video_url');
            $table->string('video_filename')->nullable()->after('video_path');
        });
    }

    public function down(): void
    {
        Schema::table('subtopics', fn (Blueprint $table) => $table->dropColumn(['video_path', 'video_filename']));
    }
};

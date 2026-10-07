<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_bank_questions', function (Blueprint $table) {
            $table->string('rationale_video_url', 2048)->nullable()->after('rationale');
            $table->string('rationale_image_path')->nullable()->after('rationale_video_url');
            $table->string('rationale_image_filename')->nullable()->after('rationale_image_path');
        });
    }

    public function down(): void
    {
        Schema::table('test_bank_questions', function (Blueprint $table) {
            $table->dropColumn(['rationale_video_url', 'rationale_image_path', 'rationale_image_filename']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('approval_status')->default('approved')->after('is_published');
            $table->timestamp('available_from')->nullable()->after('approval_status');
            $table->timestamp('available_until')->nullable()->after('available_from');
            $table->foreignId('created_by')->nullable()->after('available_until')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['approval_status', 'available_from', 'available_until']);
        });
    }
};

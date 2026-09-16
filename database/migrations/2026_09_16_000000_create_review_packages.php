<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->date('starts_at');
            $table->enum('class_type', ['Live Online', 'Face to face', 'Full Online']);
            $table->enum('status', ['draft', 'active'])->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('review_package_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('course_batches')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['review_package_id', 'batch_id']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('review_package_id')->nullable()->after('batch_id')->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->change();
            $table->index(['user_id', 'review_package_id', 'status'], 'payment_user_package_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex('payment_user_package_status_index');
            $table->dropConstrainedForeignId('review_package_id');
            $table->foreignId('batch_id')->nullable(false)->change();
        });
        Schema::dropIfExists('review_package_batches');
        Schema::dropIfExists('review_packages');
    }
};

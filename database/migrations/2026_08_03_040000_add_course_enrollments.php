<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(299)->after('description');
            $table->unsignedInteger('access_days')->default(30)->after('price');
        });

        Schema::create('course_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('voucher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        Schema::table('vouchers', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->after('id')->constrained()->nullOnDelete();
        });

        // Preserve access for users who already had the former global subscription.
        $courses = DB::table('courses')->where('is_published', true)->pluck('id');
        DB::table('users')->whereNotNull('subscription_expires_at')->orderBy('id')->each(function ($user) use ($courses) {
            foreach ($courses as $courseId) {
                DB::table('course_enrollments')->insertOrIgnore([
                    'user_id' => $user->id,
                    'course_id' => $courseId,
                    'status' => 'active',
                    'enrolled_at' => now(),
                    'expires_at' => $user->subscription_expires_at,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropConstrainedForeignId('course_id'));
        Schema::dropIfExists('course_enrollments');
        Schema::table('courses', fn (Blueprint $table) => $table->dropColumn(['price', 'access_days']));
    }
};

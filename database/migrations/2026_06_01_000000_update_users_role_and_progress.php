<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('student')->after('affiliation_type');
            $table->unsignedTinyInteger('progress_percentage')->default(0)->after('role');
            $table->unsignedInteger('modules_completed_count')->default(0)->after('progress_percentage');
            $table->string('exam_status')->default('locked')->after('modules_completed_count');
        });

        $totalTopics = (int) DB::table('topics')->count();

        $users = DB::table('users')->select('id', 'affiliation_type', 'is_admin')->get();
        foreach ($users as $user) {
            $role = 'student';
            if ($user->is_admin) {
                $role = 'admin';
            } elseif (in_array($user->affiliation_type, ['company', 'instructor'], true)) {
                $role = 'instructor';
            }

            $completedCount = (int) DB::table('user_progress')
                ->where('user_id', $user->id)
                ->count();

            $pct = $totalTopics > 0
                ? (int) round(($completedCount / $totalTopics) * 100)
                : 0;

            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'role' => $role,
                    'modules_completed_count' => $completedCount,
                    'progress_percentage' => $pct,
                    'exam_status' => $completedCount >= $totalTopics && $totalTopics > 0 ? 'eligible' : 'locked',
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'progress_percentage', 'modules_completed_count', 'exam_status']);
        });
    }
};

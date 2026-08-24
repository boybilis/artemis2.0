<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('course_enrollments', fn (Blueprint $table) => $table->dropForeign(['course_id']));
            Schema::table('course_enrollments', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'course_id']);
                $table->dropColumn('course_id');
                $table->unique(['user_id', 'batch_id']);
            });
            Schema::table('vouchers', fn (Blueprint $table) => $table->dropForeign(['course_id']));
            Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn('course_id'));
            return;
        }
        $hasUserLookup = DB::table('information_schema.statistics')->where('table_schema', DB::getDatabaseName())->where('table_name', 'course_enrollments')->where('index_name', 'course_enrollments_user_lookup')->exists();
        if (! $hasUserLookup) Schema::table('course_enrollments', fn (Blueprint $table) => $table->index('user_id', 'course_enrollments_user_lookup'));
        $hasCourseForeign = DB::table('information_schema.table_constraints')->where('constraint_schema', DB::getDatabaseName())->where('table_name', 'course_enrollments')->where('constraint_name', 'course_enrollments_course_id_foreign')->exists();
        if ($hasCourseForeign) Schema::table('course_enrollments', fn (Blueprint $table) => $table->dropForeign(['course_id']));
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'course_id']);
            $table->dropColumn('course_id');
            $table->unique(['user_id', 'batch_id']);
        });
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropForeign(['course_id']));
        Schema::table('vouchers', fn (Blueprint $table) => $table->dropColumn('course_id'));
    }

    public function down(): void
    {
        Schema::table('vouchers', fn (Blueprint $table) => $table->foreignId('course_id')->nullable()->after('id')->constrained()->cascadeOnDelete());
        Schema::table('course_enrollments', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'batch_id']);
            $table->foreignId('course_id')->nullable()->after('user_id')->constrained()->cascadeOnDelete();
            $table->unique(['user_id', 'course_id']);
        });
    }
};

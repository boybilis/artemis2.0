<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('modality');
            $table->decimal('usd_price', 10, 2)->nullable()->after('price');
        });

        if (Schema::hasColumn('courses', 'price')) {
            DB::table('course_batches')->orderBy('id')->each(function ($batch) {
                $course = DB::table('courses')->where('id', $batch->course_id)->first();
                if ($course) DB::table('course_batches')->where('id', $batch->id)->update([
                    'price' => $course->price ?? 0,
                    'usd_price' => $course->usd_price ?? null,
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('course_batches', fn (Blueprint $table) => $table->dropColumn(['price', 'usd_price']));
    }
};

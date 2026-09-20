<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_batch_courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('course_batches')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['batch_id', 'course_id']);
        });

        DB::table('course_batches')->whereNotNull('course_id')->orderBy('id')->each(function ($batch) {
            DB::table('course_batch_courses')->insertOrIgnore([
                'batch_id'=>$batch->id, 'course_id'=>$batch->course_id, 'created_at'=>now(), 'updated_at'=>now(),
            ]);
        });

        Schema::table('course_batches', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('course_batches', function (Blueprint $table) {
            $table->foreignId('course_id')->nullable(false)->change();
        });
        Schema::dropIfExists('course_batch_courses');
    }
};
